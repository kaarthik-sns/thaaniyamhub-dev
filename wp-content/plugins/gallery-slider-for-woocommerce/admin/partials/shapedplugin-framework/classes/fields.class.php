<?php
/**
 * Framework fields.class file.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access directly.

if ( ! class_exists( 'WCGS_Fields' ) ) {
	/**
	 *
	 * Fields Class
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	abstract class WCGS_Fields extends WCGS_Abstract {

		/**
		 * Field
		 *
		 * @var array
		 */
		public $field = array();

		/**
		 * Value
		 *
		 * @var string
		 */
		public $value = '';

		/**
		 * Unique
		 *
		 * @var string
		 */
		public $unique = '';

		/**
		 * Where
		 *
		 * @var string
		 */
		public $where = '';

		/**
		 * Parent
		 *
		 * @var string
		 */
		public $parent = '';

		/**
		 * Field class constructor.
		 *
		 * @param array  $field The field type.
		 * @param string $value The values of the field.
		 * @param string $unique The unique ID for the field.
		 * @param string $where To where show the output CSS.
		 * @param string $parent The parent args.
		 */
		public function __construct( $field = array(), $value = '', $unique = '', $where = '', $parent = '' ) {

			$this->field  = $field;
			$this->value  = $value;
			$this->unique = $unique;
			$this->where  = $where;
			$this->parent = $parent;
		}

		/**
		 * Field name method.
		 *
		 * @param string $nested_name Nested field name.
		 * @return string
		 */
		public function field_name( $nested_name = '' ) {

			$field_id   = ( ! empty( $this->field['id'] ) ) ? $this->field['id'] : '';
			$unique_id  = ( ! empty( $this->unique ) ) ? $this->unique . '[' . $field_id . ']' : $field_id;
			$field_name = ( ! empty( $this->field['name'] ) ) ? $this->field['name'] : $unique_id;
			$tag_prefix = ( ! empty( $this->field['tag_prefix'] ) ) ? $this->field['tag_prefix'] : '';

			if ( ! empty( $tag_prefix ) ) {
				$nested_name = str_replace( '[', '[' . $tag_prefix, $nested_name );
			}

			return $field_name . $nested_name;
		}

		/**
		 * Field attributes.
		 *
		 * @param array $custom_atts Custom attributes.
		 * @return mixed
		 */
		public function field_attributes( $custom_atts = array() ) {

			$field_id   = ( ! empty( $this->field['id'] ) ) ? $this->field['id'] : '';
			$attributes = ( ! empty( $this->field['attributes'] ) ) ? $this->field['attributes'] : array();

			if ( ! empty( $field_id ) ) {
				$attributes['data-depend-id'] = $field_id;
			}

			if ( ! empty( $this->field['placeholder'] ) ) {
				$attributes['placeholder'] = $this->field['placeholder'];
			}

			$attributes = wp_parse_args( $attributes, $custom_atts );

			$atts = '';

			if ( ! empty( $attributes ) ) {
				foreach ( $attributes as $key => $value ) {
					if ( 'only-key' === $value ) {
						$atts .= ' ' . esc_attr( $key );
					} else {
						$atts .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
					}
				}
			}

			return $atts;
		}

		/**
		 * Field before.
		 *
		 * @return mixed
		 */
		public function field_before() {
			return ( ! empty( $this->field['before'] ) ) ? $this->field['before'] : '';
		}

		/**
		 * Field after.
		 *
		 * @return mixed
		 */
		public function field_after() {

			$output  = ( ! empty( $this->field['after'] ) ) ? $this->field['after'] : '';
			$output .= ( ! empty( $this->field['desc'] ) ) ? '<p class="wcgs-text-desc">' . $this->field['desc'] . '</p>' : '';
			$output .= ( ! empty( $this->field['help'] ) ) ? '<span class="wcgs-help"><span class="wcgs-help-text">' . $this->field['help'] . '</span><span class="sp_wgs-icon-question-circle"></span></span>' : '';
			$output .= ( ! empty( $this->field['_error'] ) ) ? '<p class="wcgs-text-error">' . $this->field['_error'] . '</p>' : '';

			return $output;
		}

		// /**
		// * Field Data.
		// *
		// * @param array $type post types.
		// *
		// * @return statement
		// */
		// public function field_data( $type = '' ) {

		// $options    = array();
		// $query_args = ( ! empty( $this->field['query_args'] ) ) ? $this->field['query_args'] : array();

		// switch ( $type ) {

		// case 'page':
		// case 'pages':
		// $pages = get_pages( $query_args );

		// if ( ! is_wp_error( $pages ) && ! empty( $pages ) ) {
		// foreach ( $pages as $page ) {
		// $options[ $page->ID ] = $page->post_title;
		// }
		// }

		// break;

		// case 'post':
		// case 'posts':
		// $posts = get_posts( $query_args );

		// if ( ! is_wp_error( $posts ) && ! empty( $posts ) ) {
		// foreach ( $posts as $post ) {
		// $options[ $post->ID ] = $post->post_title;
		// }
		// }

		// break;

		// case 'category':
		// case 'categories':
		// $categories = get_categories( $query_args );

		// if ( ! is_wp_error( $categories ) && ! empty( $categories ) && ! isset( $categories['errors'] ) ) {
		// foreach ( $categories as $category ) {
		// $options[ $category->term_id ] = $category->name;
		// }
		// }

		// break;

		// case 'tag':
		// case 'tags':
		// $taxonomies = ( isset( $query_args['taxonomies'] ) ) ? $query_args['taxonomies'] : 'post_tag';
		// if ( ! isset( $query_args['taxonomy'] ) ) {
		// unset( $query_args['taxonomies'] );
		// $query_args['taxonomy'] = $taxonomies;
		// }
		// $tags = get_terms( $query_args );
		// if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
		// foreach ( $tags as $tag ) {
		// $options[ $tag->term_id ] = $tag->name;
		// }
		// }

		// break;

		// case 'menu':
		// case 'menus':
		// $menus = wp_get_nav_menus( $query_args );
		// if ( ! is_wp_error( $menus ) && ! empty( $menus ) ) {
		// foreach ( $menus as $menu ) {
		// $options[ $menu->term_id ] = $menu->name;
		// }
		// }
		// break;
		// case 'post_type':
		// case 'post_types':
		// $post_types = get_post_types(
		// array(
		// 'show_in_nav_menus' => true,
		// )
		// );

		// if ( ! is_wp_error( $post_types ) && ! empty( $post_types ) ) {
		// foreach ( $post_types as $post_type ) {
		// $options[ $post_type ] = ucfirst( $post_type );
		// }
		// }

		// break;

		// case 'sidebar':
		// case 'sidebars':
		// global $wp_registered_sidebars;

		// if ( ! empty( $wp_registered_sidebars ) ) {
		// foreach ( $wp_registered_sidebars as $sidebar ) {
		// $options[ $sidebar['id'] ] = $sidebar['name'];
		// }
		// }

		// break;

		// case 'role':
		// case 'roles':
		// global $wp_roles;

		// if ( is_object( $wp_roles ) ) {
		// $roles = $wp_roles->get_names();
		// if ( ! empty( $wp_roles ) ) {
		// foreach ( $roles as $key => $value ) {
		// $options[ $key ] = $value;
		// }
		// }
		// }

		// break;

		// default:
		// if ( function_exists( $type ) ) {
		// $options = call_user_func( $type, $this->value, $this->field );
		// }
		// break;

		// }

		// return $options;
		// }

		/**
		 * Field_data
		 *
		 * @param  mixed $type field type.
		 * @param  mixed $term term.
		 * @param  mixed $query_args array.
		 * @return array
		 */
		public static function field_data( $type = '', $term = false, $query_args = array() ) {

			$options      = array();
			$array_search = false;

			// sanitize type name.
			if ( in_array( $type, array( 'page', 'pages' ) ) ) {
				$option = 'page';
			} elseif ( in_array( $type, array( 'post', 'posts' ) ) ) {
				$option = 'post';
			} elseif ( in_array( $type, array( 'category', 'categories' ) ) ) {
				$option = 'category';
			} elseif ( in_array( $type, array( 'tag', 'tags' ) ) ) {
				$option = 'post_tag';
			} elseif ( in_array( $type, array( 'menu', 'menus' ) ) ) {
				$option = 'nav_menu';
			} else {
				$option = '';
			}

			// switch type.
			switch ( $type ) {

				case 'page':
				case 'pages':
				case 'post':
				case 'posts':
					// term query required for ajax select.
					if ( ! empty( $term ) ) {

						$query = new WP_Query(
							wp_parse_args(
								$query_args,
								array(
									's'              => $term,
									'post_type'      => $option,
									'post_status'    => 'publish',
									'posts_per_page' => 25,
								)
							)
						);

					} else {

						$query = new WP_Query(
							wp_parse_args(
								$query_args,
								array(
									'post_type'      => $option,
									'post_status'    => 'publish',
									'posts_per_page' => 100,
								)
							)
						);

					}

					if ( ! is_wp_error( $query ) && ! empty( $query->posts ) ) {
						foreach ( $query->posts as $item ) {
							$options[ $item->ID ] = $item->post_title;
						}
					}

					break;
				case 'all_post':
				case 'all_posts':
						$all_posts = get_posts( $query_args );

					if ( ! is_wp_error( $all_posts ) && ! empty( $all_posts ) ) {
						foreach ( $all_posts as $post_obj ) {
							$options[ $post_obj->ID ] = $post_obj->post_title;
						}
					}
					wp_reset_postdata();
					break;

				case 'taxonomies':
				case 'taxonomy':
					$post_types       = get_post_types( array( 'public' => true ) );
					$post_type_list   = array();
					$post_type_number = 1;
					foreach ( $post_types as $post_type => $label ) {
						$post_type_list[ $post_type_number++ ] = $label;
					}
						$taxonomy_names = get_object_taxonomies( $post_type_list['1'], 'names' );
					foreach ( $taxonomy_names as $taxonomy => $label ) {
						$options[ $label ] = $label;
					}

					break;

				case 'terms':
				case 'term':
					global $post;
					$post_types       = get_post_types( array( 'public' => true ) );
					$post_type_list   = array();
					$post_type_number = 1;
					foreach ( $post_types as $post_type => $label ) {
						$post_type_list[ $post_type_number++ ] = $label;
					}
					$taxonomy_names  = get_object_taxonomies( $post_type_list['1'], 'names' );
					$taxonomy_number = 1;
					foreach ( $taxonomy_names as $taxonomy => $label ) {
						$taxonomy_terms[ $taxonomy_number++ ] = $label;
					}
						$terms = get_terms( $taxonomy_terms['1'] );
					foreach ( $terms as $key => $value ) {
						$count                      = $value->count;
						$options[ $value->term_id ] = $value->name . '(' . $count . ')';
					}

					break;

				case 'category':
				case 'categories':
					$categories = get_categories( $query_args );

					if ( ! is_wp_error( $categories ) && ! empty( $categories ) && ! isset( $categories['errors'] ) ) {
						foreach ( $categories as $category ) {
							$count                         = $category->count;
							$options[ $category->term_id ] = $category->name . '(' . $count . ')';
						}
					}

					break;

				case 'tag':
				case 'tags':
					$taxonomies = isset( $query_args['taxonomies'] ) ? $query_args['taxonomies'] : 'post_tag';
					// Ensure $query_args is an array and set taxonomy correctly
					$query_args             = is_array( $query_args ) ? $query_args : array();
					$query_args['taxonomy'] = $taxonomies;
					$tags                   = get_terms( $query_args );
					if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
						foreach ( $tags as $tag ) {
							$count                    = $tag->count;
							$options[ $tag->term_id ] = $tag->name . '(' . $count . ')';
						}
					}

					break;

				case 'menu':
				case 'menus':
					if ( ! empty( $term ) ) {

						$query = new WP_Term_Query(
							wp_parse_args(
								$query_args,
								array(
									'search'     => $term,
									'taxonomy'   => $option,
									'hide_empty' => false,
									'number'     => 25,
								)
							)
						);

					} else {

						$query = new WP_Term_Query(
							wp_parse_args(
								$query_args,
								array(
									'taxonomy'   => $option,
									'hide_empty' => false,
								)
							)
						);

					}

					if ( ! is_wp_error( $query ) && ! empty( $query->terms ) ) {
						foreach ( $query->terms as $item ) {
							$options[ $item->term_id ] = $item->name;
						}
					}
					break;
				case 'user':
				case 'users':
					if ( ! empty( $term ) ) {

						$query = new WP_User_Query(
							array(
								'search'  => '*' . $term . '*',
								'number'  => 25,
								'orderby' => 'title',
								'order'   => 'ASC',
								'fields'  => array( 'display_name', 'ID' ),
							)
						);
					} else {
						$query = new WP_User_Query( array( 'fields' => array( 'display_name', 'ID' ) ) );
					}
					if ( ! is_wp_error( $query ) && ! empty( $query->get_results() ) ) {
						foreach ( $query->get_results() as $item ) {
							$options[ $item->ID ] = $item->display_name;
						}
					}
					break;
				case 'sidebar':
				case 'sidebars':
					global $wp_registered_sidebars;

					if ( ! empty( $wp_registered_sidebars ) ) {
						foreach ( $wp_registered_sidebars as $sidebar ) {
							$options[ $sidebar['id'] ] = $sidebar['name'];
						}
					}
					$array_search = true;
					break;

				case 'role':
				case 'roles':
					global $wp_roles;

					if ( ! empty( $wp_roles ) ) {
						if ( ! empty( $wp_roles->roles ) ) {
							foreach ( $wp_roles->roles as $role_key => $role_value ) {
								$options[ $role_key ] = $role_value['name'];
							}
						}
					}
					$array_search = true;
					break;
				case 'post_type':
				case 'post_types':
					$post_types = get_post_types( array( 'show_in_nav_menus' => true ), 'objects' );
					if ( ! is_wp_error( $post_types ) && ! empty( $post_types ) ) {
						foreach ( $post_types as $post_type ) {
							$options[ $post_type->name ] = $post_type->labels->name;
						}
						$options = apply_filters( 'wpcp_post_types_options', $options );
					}
					$array_search = true;

					break;
				case 'product_type':
				case 'product_types':
					if ( function_exists( 'wc_get_product_types' ) ) {
						$product_types = wc_get_product_types();
						foreach ( $product_types as $type => $label ) {
							$options[ $type ] = $label;
						}
					}
					break;
				default:
					if ( is_callable( $type ) ) {
						if ( ! empty( $term ) ) {
							$options = call_user_func( $type, $query_args );
						} else {
							$options = call_user_func( $type, $term, $query_args );
						}
					}

					break;

			}

			// Array search by "term".
			if ( ! empty( $term ) && ! empty( $options ) && ! empty( $array_search ) ) {
				$options = preg_grep( '/' . $term . '/i', $options );
			}

			// Make multidimensional array for ajax search.
			if ( ! empty( $term ) && ! empty( $options ) ) {
				$arr = array();
				foreach ( $options as $option_key => $option_value ) {
					$arr[] = array(
						'value' => $option_key,
						'text'  => $option_value,
					);
				}
				$options = $arr;
			}

			return $options;
		}
		/**
		 * Field_wp_query_data_title.
		 *
		 * @param  mixed $type type.
		 * @param  mixed $values value.
		 * @return array
		 */
		public function field_wp_query_data_title( $type, $values ) {

			$options = array();

			if ( ! empty( $values ) && is_array( $values ) ) {

				foreach ( $values as $value ) {

					$options[ $value ] = ucfirst( $value );

					switch ( $type ) {

						case 'post':
						case 'posts':
						case 'page':
						case 'pages':
							$title = get_the_title( $value );
							if ( ! is_wp_error( $title ) && ! empty( $title ) ) {
								$options[ $value ] = $title;
							}

							break;

						case 'category':
						case 'categories':
						case 'tag':
						case 'tags':
						case 'menu':
						case 'menus':
							$term = get_term( $value );

							if ( ! is_wp_error( $term ) && ! empty( $term ) ) {
								$options[ $value ] = $term->name;
							}

							break;

						case 'user':
						case 'users':
							$user = get_user_by( 'id', $value );

							if ( ! is_wp_error( $user ) && ! empty( $user ) ) {
								$options[ $value ] = $user->display_name;
							}

							break;

						case 'sidebar':
						case 'sidebars':
							global $wp_registered_sidebars;

							if ( ! empty( $wp_registered_sidebars[ $value ] ) ) {
									$options[ $value ] = $wp_registered_sidebars[ $value ]['name'];
							}

							break;

						case 'role':
						case 'roles':
							global $wp_roles;

							if ( ! empty( $wp_roles ) && ! empty( $wp_roles->roles ) && ! empty( $wp_roles->roles[ $value ] ) ) {
								$options[ $value ] = $wp_roles->roles[ $value ]['name'];
							}

							break;

						case 'post_type':
						case 'post_types':
							$post_types = get_post_types( array( 'show_in_nav_menus' => true ) );

							if ( ! is_wp_error( $post_types ) && ! empty( $post_types ) && ! empty( $post_types[ $value ] ) ) {
								$options[ $value ] = ucfirst( $value );
							}

							break;

						case 'location':
						case 'locations':
							$nav_menus = get_registered_nav_menus();

							if ( ! is_wp_error( $nav_menus ) && ! empty( $nav_menus ) && ! empty( $nav_menus[ $value ] ) ) {
								$options[ $value ] = $nav_menus[ $value ];
							}
							break;
						default:
							if ( is_callable( $type . '_title' ) ) {
								$options[ $value ] = call_user_func( $type . '_title', $value );
							}
							break;
					}
				}
			}

			return $options;
		}
	}
}
