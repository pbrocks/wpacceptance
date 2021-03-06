<?php
/**
 * Environment factory for storing Environments
 *
 * @package wpacceptance
 */

namespace WPAcceptance;

use Docker\Docker;

/**
 * Environment factory class
 */
class EnvironmentFactory {
	/**
	 * Registered environments
	 *
	 * @var array
	 */
	public static $environments = [];

	/**
	 * Get an environment given an index
	 *
	 * @param  int $index Environments index
	 * @return \WPAcceptance\Environment|boolean;
	 */
	public static function get( $index = 0 ) {
		if ( ! empty( self::$environments[ $index ] ) ) {
			return self::$environments[ $index ];
		}

		return false;
	}

	/**
	 * Clean up environments on shutdown
	 */
	public static function handleShutdown() {
		foreach ( self::$environments as $environment ) {
			$environment->destroy();
		}
	}

	/**
	 * Stop and destroy an environment
	 *
	 * @param  string $environment_id Environment id
	 * @return boolean
	 */
	public static function destroy( $environment_id ) {
		$environment = new Environment( null, false, false, $environment_id );

		$environment->destroy();

		return true;
	}

	/**
	 * Stop and destroy all environments
	 *
	 * @return boolean
	 */
	public static function destroyAll() {
		$docker = Docker::create();

		$containers = $docker->containerList();

		foreach ( $containers as $container ) {
			$names = $container->getNames();

			if ( ! empty( $names[0] ) && false !== strpos( $names[0], '-wpa' ) ) {
				$environment_id = preg_replace( '#^/(.*)-wpa.*$#', '$1', $names[0] );

				self::destroy( $environment_id );
			}
		}

		return true;
	}

	/**
	 * Create environment. Use cached environment if it exists
	 *
	 * @param  array   $suite_config Config array
	 * @param  boolean $cache_environment Keep containers alive or not
	 * @param  boolean $skip_environment_cache If a valid cached environment exists, don't use it. Don't cache the new environment.
	 * @param  string  $environment_id Allow for manual environment ID override
	 * @param  int     $mysql_wait_time How long should we wait for MySQL to become available (seconds)
	 * @return  \WPAcceptance\Environment|bool
	 */
	public static function create( $suite_config, $cache_environment = false, $skip_environment_cache = false, $environment_id = null, $mysql_wait_time = null ) {
		$environment = new Environment( $suite_config, $cache_environment, $skip_environment_cache, $environment_id, $mysql_wait_time );

		if ( empty( self::$environments ) ) {
			register_shutdown_function( [ '\WPAcceptance\EnvironmentFactory', 'handleShutdown' ] );
		}

		if ( $environment->populateEnvironmentFromCache() ) {
			self::$environments[] = $environment;

			$environment->stopContainers( [ 'selenium' ] );

			$environment->deleteContainers( [ 'selenium' ] );

			if ( ! $environment->createContainers( [ 'selenium' ] ) ) {
				return false;
			}

			if ( ! $environment->startContainers( [ 'selenium' ] ) ) {
				return false;
			}

			if ( ! $environment->setupHosts( false, true ) ) {
				return false;
			}

			if ( ! $environment->insertRepo() ) {
				return false;
			}

			if ( ! $environment->setupMySQL() ) {
				return false;
			}

			if ( ! $environment->runBeforeScripts() ) {
				return false;
			}
		} else {
			if ( ! $environment->createNetwork() ) {
				return false;
			}

			self::$environments[] = $environment;

			if ( ! $environment->downloadImages() ) {
				return false;
			}

			if ( ! $environment->createContainers() ) {
				return false;
			}

			if ( ! $environment->startContainers() ) {
				return false;
			}

			if ( ! $environment->pullSnapshot() ) {
				return false;
			}

			if ( ! $environment->setupHosts() ) {
				return false;
			}

			if ( ! $environment->setupMySQL() ) {
				return false;
			}

			if ( ! $environment->insertRepo() ) {
				return false;
			}

			if ( ! $environment->writeMetaToWPContainer() ) {
				return false;
			}

			if ( ! $environment->runBeforeScripts() ) {
				return false;
			}
		}

		return $environment;
	}
}
