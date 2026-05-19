<?php
/**
 * Offline JSON Schema draft 2020-12 meta-validator (issue #110 Layer 1).
 *
 * Registers the committed, public-domain 2020-12 meta-schema bundle
 * (tests/Contract/fixtures/jsonschema/2020-12/) with an opis Validator
 * so an ability's declared schema document can be validated *as data*
 * against the meta-schema — zero network, fully deterministic.
 *
 * @package Fluent_Abilities\Tests\Contract
 */

use Opis\JsonSchema\Validator;

final class JsonSchemaMeta {

	public const META_URI = 'https://json-schema.org/draft/2020-12/schema';

	/** Configured validator with the 2020-12 bundle registered offline. */
	public static function validator(): Validator {
		$dir = dirname( __DIR__ ) . '/fixtures/jsonschema/2020-12';
		$v   = new Validator();
		$r   = $v->resolver();

		$r->registerFile( self::META_URI, $dir . '/schema.json' );
		foreach ( array(
			'core',
			'applicator',
			'unevaluated',
			'validation',
			'meta-data',
			'format-annotation',
			'content',
		) as $vocab ) {
			$r->registerFile(
				'https://json-schema.org/draft/2020-12/meta/' . $vocab,
				$dir . '/meta/' . $vocab . '.json'
			);
		}
		return $v;
	}

	/**
	 * JSON Schema keywords whose value is an object/map-of-schemas. An
	 * EMPTY PHP `array()` in one of these positions is an ambiguous
	 * empty-map: it json_encodes to `[]`, which the 2020-12 meta-schema
	 * rejects (must be an object). That `[]`-vs-`{}` ambiguity is a
	 * REPRESENTATION nuance of PHP arrays, NOT the malformed-shape defect
	 * class this gate targets — so empty arrays here are normalised to
	 * empty objects before meta-validation. This is a deliberate, scoped
	 * non-catch (see tests/Contract/COVERAGE-SCOPE.md): the gate does NOT
	 * police empty `properties` encoding (the project standard "omit
	 * empty properties" is a separate lint concern). A NON-empty value in
	 * these positions is left untouched, so a genuinely malformed map
	 * (a string, or a populated list) is still caught.
	 *
	 * @var string[]
	 */
	private const OBJECT_POSITION = array(
		'properties',
		'patternProperties',
		'$defs',
		'definitions',
		'dependentSchemas',
	);

	/**
	 * Coerce a PHP array schema to the stdClass/array document opis
	 * expects, applying the scoped empty-map normalisation above.
	 * json_encode→decode is the canonical lossless transport (it mirrors
	 * how the schema is delivered over the REST Abilities API).
	 *
	 * @param mixed $schema
	 * @return mixed
	 */
	public static function toData( $schema ) {
		return json_decode( (string) json_encode( self::normalizeEmptyMaps( $schema ) ) );
	}

	/**
	 * Recursively replace `[]` with `(object) []` ONLY at object-position
	 * keywords (and only when empty). Everything else is untouched.
	 *
	 * @param mixed $node
	 * @return mixed
	 */
	private static function normalizeEmptyMaps( $node ) {
		if ( ! is_array( $node ) ) {
			return $node;
		}
		$out = array();
		foreach ( $node as $k => $v ) {
			if ( in_array( $k, self::OBJECT_POSITION, true ) && is_array( $v ) && array() === $v ) {
				$out[ $k ] = (object) array();
				continue;
			}
			$out[ $k ] = self::normalizeEmptyMaps( $v );
		}
		return $out;
	}
}
