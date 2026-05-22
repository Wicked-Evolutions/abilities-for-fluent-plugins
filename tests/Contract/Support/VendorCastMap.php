<?php
/**
 * Phase A of CrashClassScanTest (= executable #108): build, from the
 * PINNED installed-vendor source fixtures, the map
 *
 *     vendor model FQCN  =>  set of attributes the vendor itself encodes
 *
 * where "encodes" means either a serializing `$casts` entry
 * (array|json|object|collection|serialized|encrypted:* …) OR a
 * `set{X}Attribute()` mutator whose body calls
 * maybe_serialize / serialize / json_encode / wp_json_encode.
 *
 * BINDING (issue #110): the parser MUST resolve inherited / trait /
 * parent `$casts` + mutators. A class's encoded-attr set is the UNION
 * of its own and every ancestor / used-trait reachable WITHIN the
 * pinned set. If an ancestor/trait is referenced but NOT pinned, that
 * is recorded in unresolved() — the gate fails loud rather than
 * silently under-resolving (the #107-Item-4 completeness discipline).
 *
 * @package Fluent_Abilities\Tests\Contract
 */

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final class VendorCastMap {

	private const ENCODERS = array(
		'maybe_serialize',
		'serialize',
		'json_encode',
		'wp_json_encode',
	);

	/** $casts values that mean "vendor serializes this column". */
	private const ENCODING_CASTS = array(
		'array',
		'json',
		'object',
		'collection',
		'serialized',
	);

	/** @var array<string,array{own:string[],extends:?string,traits:string[],file:string,fillable:string[]}> */
	private $classes = array();

	/** @var array<string,string> short class name => FQCN (pinned only). */
	private $shortToFqcn = array();

	/** @var array<string,true> referenced ancestor/trait FQCNs not pinned. */
	private $unresolved = array();

	/** @var array<string,string[]>|null memoised resolved FQCN => attrs. */
	private $resolved = null;

	public function __construct( string $fixtureVendorDir ) {
		$parser = ( new ParserFactory() )->createForNewestSupportedVersion();
		$finder = new NodeFinder();

		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $fixtureVendorDir, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $it as $f ) {
			if ( ! $f->isFile() || 'php' !== $f->getExtension() ) {
				continue;
			}
			$ast = $parser->parse( (string) file_get_contents( $f->getPathname() ) );
			if ( null === $ast ) {
				continue;
			}
			$ns = '';
			foreach ( $finder->findInstanceOf( $ast, Node\Stmt\Namespace_::class ) as $n ) {
				$ns = $n->name ? $n->name->toString() : '';
				break;
			}
			foreach ( $finder->findInstanceOf( $ast, Node\Stmt\Class_::class ) as $class ) {
				if ( ! $class->name ) {
					continue;
				}
				$fqcn = ( '' !== $ns ? $ns . '\\' : '' ) . $class->name->toString();
				$this->classes[ $fqcn ] = array(
					'own'      => $this->ownEncodedAttrs( $class, $finder ),
					'extends'  => $class->extends ? $this->normalize( $class->extends->toString(), $ns, $ast, $finder ) : null,
					'traits'   => $this->usedTraits( $class, $ns, $ast, $finder ),
					'file'     => $f->getPathname(),
					'fillable' => $this->fillable( $class, $finder ),
				);
				$this->shortToFqcn[ $class->name->toString() ] = $fqcn;
			}
		}
	}

	/** @return array<string,string[]> resolved FQCN => sorted encoded attrs. */
	public function map(): array {
		if ( null !== $this->resolved ) {
			return $this->resolved;
		}
		$out = array();
		foreach ( array_keys( $this->classes ) as $fqcn ) {
			$attrs = array();
			$this->collect( $fqcn, $attrs, array() );
			$attrs = array_values( array_unique( $attrs ) );
			sort( $attrs );
			$out[ $fqcn ] = $attrs;
		}
		$this->resolved = $out;
		return $out;
	}

	/** Short class name => FQCN, pinned classes only. @return array<string,string> */
	public function shortNameIndex(): array {
		return $this->shortToFqcn;
	}

	/** Ancestor/trait FQCNs referenced by a pinned class but not pinned. @return string[] */
	public function unresolved(): array {
		return array_keys( $this->unresolved );
	}

	/** All encoded attribute names across the pinned set (union). @return string[] */
	public function allEncodedAttrs(): array {
		$all = array();
		foreach ( $this->map() as $attrs ) {
			foreach ( $attrs as $a ) {
				$all[ $a ] = true;
			}
		}
		$names = array_keys( $all );
		sort( $names );
		return $names;
	}

	/**
	 * Recursively merge own + parent + trait encoded attrs (the binding
	 * inherited/trait/parent resolution). Cycle-guarded.
	 *
	 * @param string   $fqcn
	 * @param string[] $acc   accumulator (by ref)
	 * @param array<string,true> $seen
	 */
	private function collect( string $fqcn, array &$acc, array $seen ): void {
		if ( isset( $seen[ $fqcn ] ) || ! isset( $this->classes[ $fqcn ] ) ) {
			return;
		}
		$seen[ $fqcn ] = true;
		$node          = $this->classes[ $fqcn ];

		foreach ( $node['own'] as $a ) {
			$acc[] = $a;
		}
		foreach ( $node['traits'] as $t ) {
			if ( isset( $this->classes[ $t ] ) ) {
				$this->collect( $t, $acc, $seen );
			} else {
				$this->unresolved[ $t ] = true;
			}
		}
		if ( null !== $node['extends'] ) {
			if ( isset( $this->classes[ $node['extends'] ] ) ) {
				$this->collect( $node['extends'], $acc, $seen );
			} else {
				$this->unresolved[ $node['extends'] ] = true;
			}
		}
	}

	/** @return string[] attrs this class itself encodes ($casts + mutators). */
	private function ownEncodedAttrs( Node\Stmt\Class_ $class, NodeFinder $finder ): array {
		$attrs = array();

		// protected $casts = [ 'attr' => 'array'|'json'|… ]
		foreach ( $finder->findInstanceOf( $class, Node\Stmt\Property::class ) as $prop ) {
			foreach ( $prop->props as $p ) {
				if ( 'casts' !== $p->name->toString() || ! $p->default instanceof Node\Expr\Array_ ) {
					continue;
				}
				foreach ( $p->default->items as $item ) {
					if ( ! $item || ! $item->key instanceof Node\Scalar\String_ ) {
						continue;
					}
					$castVal = $item->value instanceof Node\Scalar\String_
						? strtolower( $item->value->value )
						: '';
					$base = explode( ':', $castVal )[0];
					if ( in_array( $base, self::ENCODING_CASTS, true ) || 'encrypted' === $base ) {
						$attrs[] = $item->key->value;
					}
				}
			}
		}

		// set{X}Attribute() whose body calls an encoder.
		foreach ( $finder->findInstanceOf( $class, Node\Stmt\ClassMethod::class ) as $m ) {
			$name = $m->name->toString();
			if ( ! preg_match( '/^set(.+)Attribute$/', $name, $mm ) ) {
				continue;
			}
			$callsEncoder = false;
			foreach ( $finder->findInstanceOf( $m, Node\Expr\FuncCall::class ) as $call ) {
				if ( $call->name instanceof Node\Name
					&& in_array( strtolower( ltrim( $call->name->toString(), '\\' ) ), self::ENCODERS, true ) ) {
					$callsEncoder = true;
					break;
				}
			}
			if ( $callsEncoder ) {
				$attrs[] = self::snake( $mm[1] );
			}
		}

		return array_values( array_unique( $attrs ) );
	}

	/** Vendor `protected $fillable` for a pinned model FQCN. @return string[] */
	public function fillableOf( string $fqcn ): array {
		return $this->classes[ $fqcn ]['fillable'] ?? array();
	}

	/** @return string[] string entries of `protected $fillable`. */
	private function fillable( Node\Stmt\Class_ $class, NodeFinder $finder ): array {
		$out = array();
		foreach ( $finder->findInstanceOf( $class, Node\Stmt\Property::class ) as $prop ) {
			foreach ( $prop->props as $p ) {
				if ( 'fillable' !== $p->name->toString() || ! $p->default instanceof Node\Expr\Array_ ) {
					continue;
				}
				foreach ( $p->default->items as $item ) {
					if ( $item && $item->value instanceof Node\Scalar\String_ ) {
						$out[] = $item->value->value;
					}
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** @return string[] resolved trait FQCNs used by the class. */
	private function usedTraits( Node\Stmt\Class_ $class, string $ns, array $ast, NodeFinder $finder ): array {
		$out = array();
		foreach ( $finder->findInstanceOf( $class, Node\Stmt\TraitUse::class ) as $tu ) {
			foreach ( $tu->traits as $t ) {
				$out[] = $this->normalize( $t->toString(), $ns, $ast, $finder );
			}
		}
		return $out;
	}

	/**
	 * Resolve a class reference to an FQCN using the file's `use`
	 * imports, then namespace, then bare. Best-effort but deterministic.
	 */
	private function normalize( string $ref, string $ns, array $ast, NodeFinder $finder ): string {
		$ref = ltrim( $ref, '\\' );
		foreach ( $finder->findInstanceOf( $ast, Node\Stmt\Use_::class ) as $use ) {
			foreach ( $use->uses as $u ) {
				$alias = $u->alias ? $u->alias->toString() : $u->name->getLast();
				if ( $alias === $ref ) {
					return $u->name->toString();
				}
			}
		}
		if ( false === strpos( $ref, '\\' ) && '' !== $ns ) {
			return $ns . '\\' . $ref;
		}
		return $ref;
	}

	/** StudlyCase attribute segment => snake_case column. */
	private static function snake( string $studly ): string {
		return strtolower( (string) preg_replace( '/(?<!^)[A-Z]/', '_$0', $studly ) );
	}
}
