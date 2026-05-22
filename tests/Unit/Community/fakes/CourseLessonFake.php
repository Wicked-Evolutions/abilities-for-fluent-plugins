<?php
/**
 * Minimal in-memory fake of the canonical CourseLesson model.
 *
 * ONLY enough surface for the delete-lesson real-delete + read-back-gone runtime
 * test (find / __get / delete / where()->exists()). Loaded exclusively inside a
 * process-isolated test method, so it never masks the absent-vendor guards that
 * the other tests in CourseClusterFixTest assert.
 *
 * @package Fluent_Abilities\Tests\Unit\Community
 */

namespace FluentCommunity\Modules\Course\Model;

class CourseLesson {

	/** @var array<int,array> id => attributes */
	public static $rows = array();

	/** @var array */
	private $attrs;

	public function __construct( $attrs = array() ) {
		$this->attrs = $attrs;
	}

	public function __get( $k ) {
		return $this->attrs[ $k ] ?? null;
	}

	public static function find( $id ) {
		return isset( self::$rows[ $id ] ) ? new self( self::$rows[ $id ] ) : null;
	}

	public function delete() {
		unset( self::$rows[ $this->attrs['id'] ] );
		return true;
	}

	public static function where( $col, $val ) {
		return new CourseLessonFakeQuery( $col, $val );
	}
}

class CourseLessonFakeQuery {

	private $col;
	private $val;

	public function __construct( $col, $val ) {
		$this->col = $col;
		$this->val = $val;
	}

	public function exists() {
		foreach ( CourseLesson::$rows as $r ) {
			if ( ( $r[ $this->col ] ?? null ) == $this->val ) {
				return true;
			}
		}
		return false;
	}
}
