<?php
/*
Plugin Name: Option Tree Settings
Plugin URI:  http://www.wp-lion.com/
Description: Store the Option Tree settings for a single theme before this theme change, and importing the option tree settings for the theme is getting activated.
Version:     0.0.1
Author:      Merianos Nikos
Author URI:  http://www.wp-lion.com/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_action( 'after_switch_theme', 'z_after_activation', 10, 2 );

if ( ! function_exists( 'z_after_activation' ) ) {
    /**
     * Responsible to insert the current activated theme settings.
     */
    function z_after_activation( $old_theme_name, $old_theme ) {

        $config = dirname( __FILE__ ) . '/settings/' . $old_theme->get_stylesheet() . '.cnf';

        if ( file_exists( $config ) ) {
            unlink( $config );
        }

        /* get theme options data */
        $data = get_option( 'option_tree' );
        $data = ! empty( $data ) ? base64_encode( serialize( $data ) ) : '';

        $f = fopen( $config, 'w' );
        fwrite( $f, $data, strlen( $data ) );
        fclose( $f );

        $theme  = wp_get_theme();
        $config = dirname( __FILE__ ) . '/settings/' . $theme->get_stylesheet() . '.cnf';

        if ( file_exists( $config ) ) {
            $data = file_get_contents( $config );

            /* Stored values */
            $options = isset( $data ) ? unserialize( base64_decode( $data ) ) : '';

            /* get settings array */
            $settings = get_option( 'option_tree_settings' );

            /* has options */
            if ( is_array( $options ) ) {
                /* validate options */
                if ( is_array( $settings ) ) {
                    foreach ( $settings['settings'] as $setting ) {
                        if ( isset( $options[ $setting['id'] ] ) ) {
                            $content                   = ot_stripslashes( $options[ $setting['id'] ] );
                            $options[ $setting['id'] ] = ot_validate_setting( $content, $setting['type'], $setting['id'] );
                        }
                    }
                }

                /* update the option tree array */
                update_option( 'option_tree', $options );
            }
        }
    }
}

/**
 * Custom stripslashes from single value or array.
 *
 * @param       mixed $input
 *
 * @return      mixed
 *
 * @access      public
 * @since       2.0
 */
if ( ! function_exists( 'ot_stripslashes' ) ) {
    function ot_stripslashes( $input ) {
        if ( is_array( $input ) ) {
            foreach ( $input as &$val ) {
                if ( is_array( $val ) ) {
                    $val = ot_stripslashes( $val );
                } else {
                    $val = stripslashes( trim( $val ) );
                }
            }
        } else {
            $input = stripslashes( trim( $input ) );
        }

        return $input;
    }
}

/**
 * Validate the options by type before saving.
 *
 * This function will run on only some of the option types
 * as all of them don't need to be validated, just the
 * ones users are going to input data into; because they
 * can't be trusted.
 *
 * @param     mixed     Setting value
 * @param     string    Setting type
 * @param     string    Setting field ID
 * @param     string    WPML field ID
 *
 * @return    mixed
 *
 * @access    public
 * @since     2.0
 */
if ( ! function_exists( 'ot_validate_setting' ) ) {
    function ot_validate_setting( $input, $type, $field_id, $wmpl_id = '' ) {
        /* exit early if missing data */
        if ( ! $input || ! $type || ! $field_id ) {
            return $input;
        }

        $input = apply_filters( 'ot_validate_setting', $input, $type, $field_id );

        /* WPML Register and Unregister strings */
        if ( ! empty( $wmpl_id ) ) {
            /* Allow filtering on the WPML option types */
            $single_string_types = apply_filters( 'ot_wpml_option_types', array(
                'text',
                'textarea',
                'textarea-simple',
            ) );

            if ( in_array( $type, $single_string_types ) ) {
                if ( ! empty( $input ) ) {
                    ot_wpml_register_string( $wmpl_id, $input );
                } else {
                    ot_wpml_unregister_string( $wmpl_id );
                }
            }
        }

        if ( 'background' == $type ) {
            $input['background-color'] = ot_validate_setting( $input['background-color'], 'colorpicker', $field_id );
            $input['background-image'] = ot_validate_setting( $input['background-image'], 'upload', $field_id );

            // Loop over array and check for values
            foreach ( (array) $input as $key => $value ) {
                if ( ! empty( $value ) ) {
                    $has_value = true;
                }
            }

            // No value; set to empty
            if ( ! isset( $has_value ) ) {
                $input = '';
            }
        } else if ( 'border' == $type ) {
            // Loop over array and set errors or unset key from array.
            foreach ( $input as $key => $value ) {
                // Validate width
                if ( $key == 'width' && ! empty( $value ) && ! is_numeric( $value ) ) {
                    $input[ $key ] = '0';
                    add_settings_error( 'option-tree', 'invalid_border_width', sprintf( __( 'The %s input field for %s only allows numeric values.', 'option-tree' ), '<code>width</code>', '<code>' . $field_id . '</code>' ), 'error' );
                }

                // Validate color
                if ( $key == 'color' && ! empty( $value ) ) {
                    $input[ $key ] = ot_validate_setting( $value, 'colorpicker', $field_id );
                }

                // Unset keys with empty values.
                if ( empty( $value ) && strlen( $value ) == 0 ) {
                    unset( $input[ $key ] );
                }
            }

            if ( empty( $input ) ) {
                $input = '';
            }
        } else if ( 'box-shadow' == $type ) {
            // Validate inset
            $input['inset'] = isset( $input['inset'] ) ? 'inset' : '';

            // Validate offset-x
            $input['offset-x'] = ot_validate_setting( $input['offset-x'], 'text', $field_id );

            // Validate offset-y
            $input['offset-y'] = ot_validate_setting( $input['offset-y'], 'text', $field_id );

            // Validate blur-radius
            $input['blur-radius'] = ot_validate_setting( $input['blur-radius'], 'text', $field_id );

            // Validate spread-radius
            $input['spread-radius'] = ot_validate_setting( $input['spread-radius'], 'text', $field_id );

            // Validate color
            $input['color'] = ot_validate_setting( $input['color'], 'colorpicker', $field_id );

            // Unset keys with empty values.
            foreach ( $input as $key => $value ) {
                if ( empty( $value ) && strlen( $value ) == 0 ) {
                    unset( $input[ $key ] );
                }
            }

            // Set empty array to empty string.
            if ( empty( $input ) ) {
                $input = '';
            }
        } else if ( 'colorpicker' == $type ) {
            /* return empty & set error */
            if ( 0 === preg_match( '/^#([a-f0-9]{6}|[a-f0-9]{3})$/i', $input ) && 0 === preg_match( '/^rgba\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9\.]{1,4})\s*\)/i', $input ) ) {
                $input = '';
                add_settings_error( 'option-tree', 'invalid_hex', sprintf( __( 'The %s Colorpicker only allows valid hexadecimal or rgba values.', 'option-tree' ), '<code>' . $field_id . '</code>' ), 'error' );
            }
        } else if ( 'colorpicker-opacity' == $type ) {
            // Not allowed
            if ( is_array( $input ) ) {
                $input = '';
            }

            // Validate color
            $input = ot_validate_setting( $input, 'colorpicker', $field_id );
        } else if ( in_array( $type, array( 'css', 'javascript', 'text', 'textarea', 'textarea-simple' ) ) ) {
            if ( ! current_user_can( 'unfiltered_html' ) && OT_ALLOW_UNFILTERED_HTML == false ) {
                $input = wp_kses_post( $input );
            }
        } else if ( 'dimension' == $type ) {

            // Loop over array and set error keys or unset key from array.
            foreach ( $input as $key => $value ) {
                if ( ! empty( $value ) && ! is_numeric( $value ) && $key !== 'unit' ) {
                    $errors[] = $key;
                }

                if ( empty( $value ) && strlen( $value ) == 0 ) {
                    unset( $input[ $key ] );
                }
            }

            /* return 0 & set error */
            if ( isset( $errors ) ) {
                foreach ( $errors as $error ) {
                    $input[ $error ] = '0';
                    add_settings_error( 'option-tree', 'invalid_dimension_' . $error, sprintf( __( 'The %s input field for %s only allows numeric values.', 'option-tree' ), '<code>' . $error . '</code>', '<code>' . $field_id . '</code>' ), 'error' );
                }
            }

            if ( empty( $input ) ) {
                $input = '';
            }
        } else if ( 'google-fonts' == $type ) {
            unset( $input['%key%'] );

            // Loop over array and check for values
            if ( is_array( $input ) && ! empty( $input ) ) {
                $input = array_values( $input );
            }

            // No value; set to empty
            if ( empty( $input ) ) {
                $input = '';
            }
        } else if ( 'link-color' == $type ) {
            // Loop over array and check for values
            if ( is_array( $input ) && ! empty( $input ) ) {
                foreach ( $input as $key => $value ) {
                    if ( ! empty( $value ) ) {
                        $input[ $key ] = ot_validate_setting( $input[ $key ], 'colorpicker', $field_id . '-' . $key );
                        $has_value     = true;
                    }
                }
            }

            // No value; set to empty
            if ( ! isset( $has_value ) ) {
                $input = '';
            }
        } else if ( 'measurement' == $type ) {
            $input[0] = sanitize_text_field( $input[0] );

            // No value; set to empty
            if ( empty( $input[0] ) && strlen( $input[0] ) == 0 && empty( $input[1] ) ) {
                $input = '';
            }
        } else if ( 'spacing' == $type ) {
            // Loop over array and set error keys or unset key from array.
            foreach ( $input as $key => $value ) {
                if ( ! empty( $value ) && ! is_numeric( $value ) && $key !== 'unit' ) {
                    $errors[] = $key;
                }

                if ( empty( $value ) && strlen( $value ) == 0 ) {
                    unset( $input[ $key ] );
                }
            }

            /* return 0 & set error */
            if ( isset( $errors ) ) {
                foreach ( $errors as $error ) {
                    $input[ $error ] = '0';
                    add_settings_error( 'option-tree', 'invalid_spacing_' . $error, sprintf( __( 'The %s input field for %s only allows numeric values.', 'option-tree' ), '<code>' . $error . '</code>', '<code>' . $field_id . '</code>' ), 'error' );
                }
            }

            if ( empty( $input ) ) {
                $input = '';
            }
        } else if ( 'typography' == $type && isset( $input['font-color'] ) ) {

            $input['font-color'] = ot_validate_setting( $input['font-color'], 'colorpicker', $field_id );

            // Loop over array and check for values
            foreach ( $input as $key => $value ) {
                if ( ! empty( $value ) ) {
                    $has_value = true;
                }
            }

            // No value; set to empty
            if ( ! isset( $has_value ) ) {
                $input = '';
            }
        } else if ( 'upload' == $type ) {
            if ( filter_var( $input, FILTER_VALIDATE_INT ) === false ) {
                $input = esc_url_raw( $input );
            }
        } else if ( 'gallery' == $type ) {
            $input = trim( $input );
        } else if ( 'social-links' == $type ) {

            // Loop over array and check for values, plus sanitize the text field
            foreach ( (array) $input as $key => $value ) {
                if ( ! empty( $value ) && is_array( $value ) ) {
                    foreach ( (array) $value as $item_key => $item_value ) {
                        if ( ! empty( $item_value ) ) {
                            $has_value                  = true;
                            $input[ $key ][ $item_key ] = sanitize_text_field( $item_value );
                        }
                    }
                }
            }

            // No value; set to empty
            if ( ! isset( $has_value ) ) {
                $input = '';
            }
        }

        $input = apply_filters( 'ot_after_validate_setting', $input, $type, $field_id );

        return $input;
    }
}
