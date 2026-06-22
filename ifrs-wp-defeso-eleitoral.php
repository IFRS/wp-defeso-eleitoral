<?php
/*
 * Plugin Name:       IFRS Defeso Eleitoral
 * Plugin URI:        https://github.com/IFRS/wp-defeso-eleitoral
 * Description:       Oculta conteúdos anteriores à data de corte em toda a rede multisite, para atender à legislação eleitoral brasileira.
 * Version:           1.0.0
 * Requires at least: 5.3
 * Requires PHP:      8.0
 * Author:            Ricardo Moro
 * Author URI:        https://github.com/ricardomoro
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       ifrs-wp-defeso-eleitoral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'IFRS_DEFESO_ENABLED' ) ) {
	define( 'IFRS_DEFESO_ENABLED', true );
}

if ( ! defined( 'IFRS_DEFESO_CUTOFF' ) ) {
	define( 'IFRS_DEFESO_CUTOFF', '2026-01-01 00:00:00' );
}

if ( ! defined( 'IFRS_DEFESO_POST_TYPES' ) ) {
	define( 'IFRS_DEFESO_POST_TYPES', array( 'post' ) );
}

if ( ! defined( 'IFRS_DEFESO_BLOCK_SINGLES' ) ) {
	define( 'IFRS_DEFESO_BLOCK_SINGLES', true );
}

if ( ! defined( 'IFRS_DEFESO_APPLY_TO_FEEDS' ) ) {
	define( 'IFRS_DEFESO_APPLY_TO_FEEDS', true );
}

if ( ! class_exists( 'IFRS_Defeso_Eleitoral_MU' ) ) {
	final class IFRS_Defeso_Eleitoral_MU {

		public static function init() {
			if ( ! IFRS_DEFESO_ENABLED ) {
				return;
			}

			add_action( 'pre_get_posts', array( __CLASS__, 'filter_main_query' ) );
			add_filter( 'posts_clauses', array( __CLASS__, 'filter_posts_clauses' ), 10, 2 );
			add_action( 'template_redirect', array( __CLASS__, 'block_old_singulars' ), 0 );
			add_filter( 'rest_post_query', array( __CLASS__, 'filter_rest_query' ), 10, 2 );
			add_filter( 'rest_post_search_query', array( __CLASS__, 'filter_rest_search_query' ), 10, 2 );
		}

		private static function post_types() {
			$types = IFRS_DEFESO_POST_TYPES;

			if ( ! is_array( $types ) ) {
				$types = array( 'post' );
			}

			return array_values(
				array_unique(
					array_filter(
						array_map( 'sanitize_key', $types )
					)
				)
			);
		}

		private static function cutoff_mysql_for_site() {
			$timezone = wp_timezone();
			$cutoff   = date_create( IFRS_DEFESO_CUTOFF, $timezone );

			if ( false === $cutoff ) {
				$cutoff = date_create( '2026-01-01 00:00:00', $timezone );
			}

			return $cutoff->format( 'Y-m-d H:i:s' );
		}

		private static function cutoff_timestamp_for_site() {
			$timezone = wp_timezone();
			$cutoff   = date_create( IFRS_DEFESO_CUTOFF, $timezone );

			if ( false === $cutoff ) {
				$cutoff = date_create( '2026-01-01 00:00:00', $timezone );
			}

			return $cutoff->getTimestamp();
		}

		private static function cutoff_rule() {
			return array(
				'column'    => 'post_date',
				'compare'   => '>=',
				'mysql'     => self::cutoff_mysql_for_site(),
				'timestamp' => self::cutoff_timestamp_for_site(),
			);
		}

		private static function build_date_query_clause() {
			$rule = self::cutoff_rule();

			return array(
				'after'     => $rule['mysql'],
				'inclusive' => true,
				'column'    => $rule['column'],
			);
		}

		private static function post_matches_cutoff( $post ) {
			$rule           = self::cutoff_rule();
			$post_timestamp = get_post_time( 'U', false, $post );

			return $post_timestamp >= $rule['timestamp'];
		}

		private static function query_targets_post_type( $query ) {
			$target_types = self::post_types();
			$post_type    = $query->get( 'post_type' );

			if ( empty( $post_type ) ) {
				return in_array( 'post', $target_types, true );
			}

			if ( 'any' === $post_type ) {
				return in_array( 'post', $target_types, true );
			}

			$current_types = is_array( $post_type ) ? $post_type : array( $post_type );

			foreach ( $current_types as $type ) {
				if ( in_array( $type, $target_types, true ) ) {
					return true;
				}
			}

			return false;
		}

		private static function should_filter_query( $query ) {
			if ( is_admin() || ! $query->is_main_query() ) {
				return false;
			}

			if ( $query->is_feed() && ! IFRS_DEFESO_APPLY_TO_FEEDS ) {
				return false;
			}

			if ( ! self::query_targets_post_type( $query ) ) {
				return false;
			}

			return (
				$query->is_home() ||
				$query->is_archive() ||
				$query->is_search() ||
				( IFRS_DEFESO_APPLY_TO_FEEDS && $query->is_feed() )
			);
		}

		public static function filter_main_query( $query ) {
			if ( ! self::should_filter_query( $query ) ) {
				return;
			}

			$date_query = $query->get( 'date_query' );

			if ( ! is_array( $date_query ) ) {
				$date_query = array();
			}

			$date_query[] = self::build_date_query_clause();
			$query->set( 'date_query', $date_query );
		}

		public static function filter_posts_clauses( $clauses, $query ) {
			if ( is_admin() || ! ( $query instanceof WP_Query ) ) {
				return $clauses;
			}

			if ( ! self::query_targets_post_type( $query ) ) {
				return $clauses;
			}

			global $wpdb;
			$rule = self::cutoff_rule();

			$clauses['where'] .= $wpdb->prepare(
				' AND ' . $wpdb->posts . '.' . $rule['column'] . ' ' . $rule['compare'] . ' %s',
				$rule['mysql']
			);

			return $clauses;
		}

		public static function block_old_singulars() {
			if ( is_admin() || ! IFRS_DEFESO_BLOCK_SINGLES ) {
				return;
			}

			if ( ! is_singular( self::post_types() ) ) {
				return;
			}

			$post = get_queried_object();

			if ( ! ( $post instanceof WP_Post ) ) {
				return;
			}

			if ( ! self::post_matches_cutoff( $post ) ) {
				status_header( 451 );
				nocache_headers();
				include get_query_template( '404' );
				exit;
			}
		}

		public static function filter_rest_query( $args, $request ) {
			$target_types = self::post_types();

			if ( ! in_array( 'post', $target_types, true ) ) {
				return $args;
			}

			if ( ! isset( $args['date_query'] ) || ! is_array( $args['date_query'] ) ) {
				$args['date_query'] = array();
			}

			$args['date_query'][] = self::build_date_query_clause();

			return $args;
		}

		public static function filter_rest_search_query( $args, $request ) {
			if ( ! isset( $args['date_query'] ) || ! is_array( $args['date_query'] ) ) {
				$args['date_query'] = array();
			}

			$args['date_query'][] = self::build_date_query_clause();

			return $args;
		}
	}

	IFRS_Defeso_Eleitoral_MU::init();
}
