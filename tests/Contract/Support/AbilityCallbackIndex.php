<?php
/**
 * Indexes ability registrations from SOURCE for the V7/V8 static
 * checks: every `$reg->read|write|delete( 'slug', [ … ] )` call, with
 * its slug, the `'callback'` closure node, and the `'input_schema'`
 * array node — extracted via php-parser (the registered
 * execute_callback is the registrar WRAPPER closure, so the guard /
 * input handling lives only in this original source literal).
 *
 * Static only: nodes are inspected, never executed.
 *
 * @package Fluent_Abilities\Tests\Contract
 */

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final class AbilityCallbackIndex {

	/** @var array<int,array{slug:string,verb:string,file:string,line:int,callback:Node\Expr\Closure|Node\Expr\ArrowFunction,schema:?Node\Expr\Array_}> */
	private $entries = array();

	/** @param string[] $files Absolute PHP paths. */
	public function __construct( array $files ) {
		$parser = ( new ParserFactory() )->createForNewestSupportedVersion();
		$finder = new NodeFinder();

		foreach ( $files as $file ) {
			$ast = $parser->parse( (string) file_get_contents( $file ) );
			if ( null === $ast ) {
				continue;
			}
			foreach ( $finder->findInstanceOf( $ast, Node\Expr\MethodCall::class ) as $call ) {
				if ( ! $call->name instanceof Node\Identifier ) {
					continue;
				}
				$verb = strtolower( $call->name->toString() );
				if ( ! in_array( $verb, array( 'read', 'write', 'delete' ), true ) ) {
					continue;
				}
				$args = $call->args;
				if ( count( $args ) < 2
					|| ! $args[0]->value instanceof Node\Scalar\String_
					|| ! $args[1]->value instanceof Node\Expr\Array_ ) {
					continue;
				}
				$slug   = $args[0]->value->value;
				$config = $args[1]->value;
				if ( false === strpos( $slug, '/' ) ) {
					continue; // not an ability slug
				}

				$cb     = null;
				$schema = null;
				foreach ( $config->items as $item ) {
					if ( ! $item || ! $item->key instanceof Node\Scalar\String_ ) {
						continue;
					}
					if ( 'callback' === $item->key->value
						&& ( $item->value instanceof Node\Expr\Closure
							|| $item->value instanceof Node\Expr\ArrowFunction ) ) {
						$cb = $item->value;
					}
					if ( 'input_schema' === $item->key->value
						&& $item->value instanceof Node\Expr\Array_ ) {
						$schema = $item->value;
					}
				}
				if ( null === $cb ) {
					continue;
				}
				$this->entries[] = array(
					'slug'     => $slug,
					'verb'     => $verb,
					'file'     => $file,
					'line'     => $call->getLine(),
					'callback' => $cb,
					'schema'   => $schema,
				);
			}
		}
	}

	/** @return array<int,array{slug:string,verb:string,file:string,line:int,callback:mixed,schema:?Node\Expr\Array_}> */
	public function all(): array {
		return $this->entries;
	}

	/** Does an input_schema array node declare a given property key anywhere? */
	public static function schemaDeclaresKey( ?Node\Expr\Array_ $schema, string $key ): bool {
		if ( null === $schema ) {
			return false;
		}
		$finder = new NodeFinder();
		foreach ( $finder->findInstanceOf( $schema, Node\Expr\ArrayItem::class ) as $it ) {
			if ( $it->key instanceof Node\Scalar\String_ && $key === $it->key->value ) {
				return true;
			}
		}
		return false;
	}
}
