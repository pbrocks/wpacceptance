<?php
/**
 * URL contains constraint
 *
 * @package wpacceptance
 */

namespace WPAcceptance\PHPUnit\Constraints;

use WPAcceptance\Utils;

/**
 * Constraint class
 */
class UrlContains extends \WPAcceptance\PHPUnit\Constraint {

	/**
	 * The text to look for.
	 *
	 * @access private
	 * @var string
	 */
	private $text;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @param string $action The evaluation action. Valid options are "see" or "dontSee".
	 * @param string $text A text to look for.
	 */
	public function __construct( $action, $text ) {
		parent::__construct( $action );

		$this->text = $text;
	}

	/**
	 * Evaluate if the actor can or can't see a text in the current URL.
	 *
	 * @access protected
	 * @param \WPAcceptance\PHPUnit\Actor $other The actor instance.
	 * @return boolean TRUE if the constrain is met, otherwise FALSE.
	 */
	protected function matches( $other ): bool {
		$actor     = $this->getActor( $other );
		$webdriver = $actor->getWebDriver();

		$url   = trim( $webdriver->getCurrentURL() );
		$found = Utils\find_match( $url, $this->text );

		return ( $found && self::ACTION_SEE === $this->action ) || ( ! $found && self::ACTION_DONTSEE === $this->action );
	}

	/**
	 * Return description of the failure.
	 *
	 * @access public
	 * @return string The description text.
	 */
	public function toString(): string {
		return sprintf( ' "%s" text in the current URL', $this->text );
	}

}
