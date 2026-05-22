<?php
/**
 * Phase B of CrashClassScanTest (= executable #108): scan OUR code for
 * the #106/#107 crash class — a value the code itself already encoded
 * (maybe_serialize / serialize / json_encode / wp_json_encode) being
 * assigned/passed into a vendor model attribute that the vendor ALSO
 * encodes (per VendorCastMap). The vendor mutator/$cast encodes a
 * second time → read returns a string where the vendor expects an
 * array → count()/Arr::get() fatal (the production 500).
 *
 * Detection is intraprocedural and class-resolved (no bare attr-name
 * heuristic — that would false-positive on the 54 correct raw-table
 * writers). A model variable is bound to a vendor FQCN when it is
 * assigned from `\FQCN::find|create|firstOrNew|firstOrCreate|
 * updateOrCreate|query|new \FQCN`. A hit is a pre-encoded value:
 *   • assigned to `$model->encodedAttr`, or
 *   • given as `'encodedAttr' => <encoded>` in the array passed to
 *     `\FQCN::create([...])` / `$model->fill([...])` / `->update([...])`.
 *
 * Honest scope (tests/Contract/COVERAGE-SCOPE.md): catches the exact
 * #106/#107 shape against the PINNED vendor models with intraprocedural
 * var→class binding. Cross-function indirection / un-pinned vendor
 * models / non-listed encoders are explicitly out of deterministic
 * scope and tracked by follow-up #108.
 *
 * @package Fluent_Abilities\Tests\Contract
 */

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

final class CrashClassScanner {

	private const ENCODERS = array(
		'maybe_serialize',
		'serialize',
		'json_encode',
		'wp_json_encode',
	);

	private const MODEL_ENTRY = array(
		'find',
		'create',
		'firstornew',
		'firstorcreate',
		'updateorcreate',
		'query',
		'findorfail',
	);

	/** @var array<string,string[]> FQCN => encoded attrs (from VendorCastMap). */
	private $vendorMap;

	/** @var array<string,string> short class name => FQCN. */
	private $shortIndex;

	/** @var array<int,array{file:string,line:int,detail:string}> */
	private $hits = array();

	/**
	 * @param array<string,string[]> $vendorMap
	 * @param array<string,string>   $shortIndex
	 */
	public function __construct( array $vendorMap, array $shortIndex ) {
		$this->vendorMap  = $vendorMap;
		$this->shortIndex = $shortIndex;
	}

	/** @param string[] $files Absolute PHP paths to scan. */
	public function scan( array $files ): void {
		$parser = ( new ParserFactory() )->createForNewestSupportedVersion();
		foreach ( $files as $file ) {
			$code = (string) file_get_contents( $file );
			$ast  = $parser->parse( $code );
			if ( null === $ast ) {
				continue;
			}
			$visitor = new class( $file, $this ) extends NodeVisitorAbstract {
				private $file;
				private $owner;
				public function __construct( string $file, CrashClassScanner $owner ) {
					$this->file  = $file;
					$this->owner = $owner;
				}
				public function enterNode( Node $node ) {
					if ( $node instanceof Node\Stmt\Function_
						|| $node instanceof Node\Stmt\ClassMethod
						|| $node instanceof Node\Expr\Closure
						|| $node instanceof Node\Expr\ArrowFunction ) {
						$this->owner->inspectScope( $node, $this->file );
					}
					return null;
				}
			};
			$traverser = new NodeTraverser();
			$traverser->addVisitor( $visitor );
			$traverser->traverse( $ast );
		}
	}

	/** @return array<int,array{file:string,line:int,detail:string}> */
	public function hits(): array {
		return $this->hits;
	}

	/**
	 * Inspect one function-like scope: bind model vars, then flag any
	 * pre-encoded value reaching a vendor encoded attribute.
	 *
	 * @param Node\FunctionLike $scope
	 */
	public function inspectScope( Node\FunctionLike $scope, string $file ): void {
		$finder = new NodeFinder();
		$body   = $scope->getStmts() ?? array();

		// Pass 1 — bind $var => vendor FQCN from model entrypoints.
		$varClass = array();
		foreach ( $finder->findInstanceOf( $body, Node\Expr\Assign::class ) as $as ) {
			if ( ! $as->var instanceof Node\Expr\Variable || ! is_string( $as->var->name ) ) {
				continue;
			}
			$fqcn = $this->resolveModelExpr( $as->expr );
			if ( null !== $fqcn ) {
				$varClass[ $as->var->name ] = $fqcn;
			}
		}

		// Pass 2 — pre-encoded direct assignment: $model->attr = <enc>.
		foreach ( $finder->findInstanceOf( $body, Node\Expr\Assign::class ) as $as ) {
			if ( ! $as->var instanceof Node\Expr\PropertyFetch
				|| ! $as->var->var instanceof Node\Expr\Variable
				|| ! is_string( $as->var->var->name )
				|| ! $as->var->name instanceof Node\Identifier ) {
				continue;
			}
			$vn = $as->var->var->name;
			if ( ! isset( $varClass[ $vn ] ) ) {
				continue;
			}
			$fqcn = $varClass[ $vn ];
			$attr = $as->var->name->toString();
			if ( ! $this->isEncodedAttr( $fqcn, $attr ) ) {
				continue;
			}
			if ( $this->isPreEncoded( $as->expr, $body, $finder ) ) {
				$this->record( $file, $as->getLine(), "\${$vn} (" . $this->short( $fqcn ) . ")->{$attr} = <pre-encoded>" );
			}
		}

		// Pass 3 — array into a vendor write: ::create / ->fill / ->update.
		foreach ( $finder->findInstanceOf( $body, Node\Expr\StaticCall::class ) as $call ) {
			if ( ! $call->name instanceof Node\Identifier
				|| 'create' !== strtolower( $call->name->toString() )
				|| ! $call->class instanceof Node\Name ) {
				continue;
			}
			$fqcn = $this->matchVendor( ltrim( $call->class->toString(), '\\' ) );
			if ( null !== $fqcn ) {
				$this->checkArrayArg( $call->args, $fqcn, $body, $finder, $file, "{$this->short($fqcn)}::create([…])" );
			}
		}
		foreach ( $finder->findInstanceOf( $body, Node\Expr\MethodCall::class ) as $call ) {
			if ( ! $call->name instanceof Node\Identifier
				|| ! in_array( strtolower( $call->name->toString() ), array( 'fill', 'update' ), true )
				|| ! $call->var instanceof Node\Expr\Variable
				|| ! is_string( $call->var->name )
				|| ! isset( $varClass[ $call->var->name ] ) ) {
				continue;
			}
			$fqcn = $varClass[ $call->var->name ];
			$this->checkArrayArg(
				$call->args,
				$fqcn,
				$body,
				$finder,
				$file,
				"\${$call->var->name}->" . strtolower( $call->name->toString() ) . '([…])'
			);
		}
	}

	/** @param Node\Arg[] $args */
	private function checkArrayArg( array $args, string $fqcn, $body, NodeFinder $finder, string $file, string $where ): void {
		foreach ( $args as $arg ) {
			if ( ! $arg->value instanceof Node\Expr\Array_ ) {
				continue;
			}
			foreach ( $arg->value->items as $item ) {
				if ( ! $item || ! $item->key instanceof Node\Scalar\String_ ) {
					continue;
				}
				$attr = $item->key->value;
				if ( $this->isEncodedAttr( $fqcn, $attr )
					&& $this->isPreEncoded( $item->value, $body, $finder ) ) {
					$this->record( $file, $item->getLine(), "{$where} 'on key' {$attr} => <pre-encoded>" );
				}
			}
		}
	}

	/** A static-call/new that yields a pinned vendor model instance. */
	private function resolveModelExpr( Node $expr ): ?string {
		if ( $expr instanceof Node\Expr\StaticCall
			&& $expr->class instanceof Node\Name
			&& $expr->name instanceof Node\Identifier
			&& in_array( strtolower( $expr->name->toString() ), self::MODEL_ENTRY, true ) ) {
			return $this->matchVendor( ltrim( $expr->class->toString(), '\\' ) );
		}
		if ( $expr instanceof Node\Expr\New_ && $expr->class instanceof Node\Name ) {
			return $this->matchVendor( ltrim( $expr->class->toString(), '\\' ) );
		}
		// Fluent chains: \FQCN::query()->where(...)->first() etc.
		if ( $expr instanceof Node\Expr\MethodCall ) {
			return $this->resolveModelExpr( $expr->var );
		}
		if ( $expr instanceof Node\Expr\StaticCall && $expr->class instanceof Node\Name ) {
			return $this->matchVendor( ltrim( $expr->class->toString(), '\\' ) );
		}
		return null;
	}

	/** Map a (possibly short/aliased) class ref to a pinned vendor FQCN. */
	private function matchVendor( string $ref ): ?string {
		if ( isset( $this->vendorMap[ $ref ] ) ) {
			return $ref;
		}
		$short = false !== strpos( $ref, '\\' ) ? substr( strrchr( $ref, '\\' ), 1 ) : $ref;
		if ( isset( $this->shortIndex[ $short ] ) && isset( $this->vendorMap[ $this->shortIndex[ $short ] ] ) ) {
			return $this->shortIndex[ $short ];
		}
		return null;
	}

	private function isEncodedAttr( string $fqcn, string $attr ): bool {
		return isset( $this->vendorMap[ $fqcn ] ) && in_array( $attr, $this->vendorMap[ $fqcn ], true );
	}

	/**
	 * True when $expr is itself an encoder call, or a variable whose
	 * nearest in-scope assignment is an encoder call.
	 */
	private function isPreEncoded( Node $expr, $body, NodeFinder $finder ): bool {
		if ( $this->isEncoderCall( $expr ) ) {
			return true;
		}
		if ( $expr instanceof Node\Expr\Variable && is_string( $expr->name ) ) {
			$last = null;
			foreach ( $finder->findInstanceOf( $body, Node\Expr\Assign::class ) as $as ) {
				if ( $as->var instanceof Node\Expr\Variable
					&& $as->var->name === $expr->name
					&& $as->getLine() <= $expr->getLine() ) {
					$last = $as;
				}
			}
			if ( null !== $last && $this->isEncoderCall( $last->expr ) ) {
				return true;
			}
		}
		return false;
	}

	private function isEncoderCall( Node $e ): bool {
		return $e instanceof Node\Expr\FuncCall
			&& $e->name instanceof Node\Name
			&& in_array( strtolower( ltrim( $e->name->toString(), '\\' ) ), self::ENCODERS, true );
	}

	private function record( string $file, int $line, string $detail ): void {
		$this->hits[] = array( 'file' => $file, 'line' => $line, 'detail' => $detail );
	}

	private function short( string $fqcn ): string {
		return false !== strpos( $fqcn, '\\' ) ? substr( strrchr( $fqcn, '\\' ), 1 ) : $fqcn;
	}
}
