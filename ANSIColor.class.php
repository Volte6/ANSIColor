<?php
//
// ANSIColor::parse('<ansi clear="true"></ansi><ansi center="true"><ansi fg="200" bold="true">TE<ansi fg="red" bold="true">S</ansi>T</ansi></ansi>'); // converts ansi tags into valid ANSI escape sequences
// ANSIColor::strip( $text ); // strips ansi out of string
// ANSIColor::strlen($text); // gives the actual character length of a string regardless of ansi tags/escape codes contained within
//
// See ::parse() function for usage examples in switch statement
//

class ANSIColor
{

	private static $screen_width		= 80;
	private static $mb_functions		= null;

	private static $total_parse_calls	= 0;
	private static $total_parse_time	= 0;
	private static $total_color_calls	= 0;
	private static $total_color_time	= 0;

	private static $color_aliases		= array(); // alias => color mapping

	const NORMAL			= 0;
	const BOLD				= 1;
	const BG_BOLD			= 1000001;
	const UNDERLINE			= 4;
	const BLINK				= 5;
	const INVERT			= 7;

	const FG_BLACK			= 30;
	const FG_RED			= 31;
	const FG_GREEN			= 32;
	const FG_YELLOW			= 33;
	const FG_BLUE			= 34;
	const FG_MAGENTA		= 35;
	const FG_CYAN			= 36;
	const FG_WHITE			= 37;

	const BG_START			= 40;
	const BG_BLACK			= 40;
	const BG_RED			= 41;
	const BG_GREEN			= 42;
	const BG_YELLOW			= 43;
	const BG_BLUE			= 44;
	const BG_MAGENTA		= 45;
	const BG_CYAN			= 46;
	const BG_WHITE			= 47;

	const BG_END			= 48;

	// some shortcut helpers
	const FG_BOLD_BLACK		= 1030;
	const FG_BOLD_RED		= 1031;
	const FG_BOLD_GREEN		= 1032;
	const FG_BOLD_YELLOW	= 1033;
	const FG_BOLD_BLUE		= 1034;
	const FG_BOLD_MAGENTA	= 1035;
	const FG_BOLD_CYAN		= 1036;
	const FG_BOLD_WHITE		= 1037;

	// helper aliases for future consistency
	const FG_DATE			= AnsiColor::FG_MAGENTA;
	const FG_BOLD_DATE		= AnsiColor::FG_BOLD_MAGENTA;
	const FG_USERNAME		= AnsiColor::FG_CYAN;
	const FG_BOLD_USERNAME	= AnsiColor::FG_BOLD_CYAN;
	const FG_GOOD			= AnsiColor::FG_GREEN;
	const FG_BOLD_GOOD		= AnsiColor::FG_BOLD_GREEN;
	const FG_BAD			= AnsiColor::FG_RED;
	const FG_BOLD_BAD		= AnsiColor::FG_BOLD_RED;
	const FG_DEAD			= AnsiColor::FG_BOLD_BLACK;

	/*
	 * Alias a string to a color string
	 */
	public static function colorAlias( $alias, $color ) {

		if ( substr($color, 0, 3) == 'FG_' ) $color = substr($color, 3);
		if ( substr($color, 0, 3) == 'BG_' ) $color = substr($color, 3);

		if ( !defined('self::FG_'. $color) && !defined('self::BG_'. $color) ) {
			throw new Exception('Invalid color specified for alias in ANSIColor::colorAlias()');
		}

		self::$color_aliases[ $alias ] = $color;

	}

	public static function strip( $input ) {

		if ( stripos($input, '<ansi') !== false ) {
			$input = self::parse($input);
		}

		return preg_replace("/(?:\e\[(.*?)m|(\x08))/", '', $input);
	}

	public static function strlen( $input ) {

		$tmpstr				= self::strip( (string)$input );

		$backspace_count	= substr_count($tmpstr, chr(8));

		return self::mb_strlen($tmpstr) - $backspace_count;
	}

	public static function parse($text) {

		self::$total_parse_calls++;

		$clear_screen		= false;
		$color_time_before	= self::$total_color_time;
		$parse_start		= microtime(true);

		while( preg_match_all('/(<ansi(?:[^>]|arg>)*["\']\s*>)([^<]*)<\/ansi\s*>/mi', $text, $tags, PREG_SET_ORDER) ) {

			foreach( $tags as $tag_match ) {

				preg_match_all('/([a-zA-Z_]+)\s*=\s*["\'](.+)["\']/Um', $tag_match[1], $properties, PREG_SET_ORDER);

				$COLOR_ARGS = array();

				foreach( $properties AS $prop ) {

					$prop_name	= strtoupper($prop[1]);
					$prop_args	= $prop[2];

					// split on any commas not preceded by a backslash
					if ( preg_match('/(?<!\\\),/', $prop_args) ) {
						$prop_args = preg_split('/(?<!\\\),/', $prop_args);
					}

					if ( !is_array($prop_args) ) {
						$prop_args = array($prop_args);
					}

					switch( $prop_name ) {

						//
						// Sets target width for actions like centering.
						// 	<ansi width="80"></ansi>
						//
						case "WIDTH":
							ANSIColor::$screen_width = max(10, $prop_args[0]);
							break;

						//
						// Sets foreground or background color
						// 	<ansi fg="blue" bg="red"></ansi>
						//
						// 8-bit values: ( https://en.wikipedia.org/wiki/ANSI_escape_code#8-bit )
						// 	<ansi fg="200"></ansi>
						//
						case 'FG':
						case 'BG':

							if ( isset( self::$color_aliases[ $prop_args[0] ] ) ) {
								$prop_args[0] = self::$color_aliases[ $prop_args[0] ];
							}

							$prop_args[0] = strtoupper($prop_args[0]);

							if ( is_numeric($prop_args[0]) ) {

								if ( $prop_name == 'FG' ) {
									$COLOR_ARGS[] = '38;5;' . $prop_args[0];
								}
								else {
									$COLOR_ARGS[] = '48;5;' . $prop_args[0];
								}
							}
							else {

								if ( substr($prop_args[0], 0, 3) != $prop_name . '_' ) {
									$prop_args[0] = $prop_name . '_' . $prop_args[0];
								}

								if ( defined('self::'. $prop_args[0]) ) {
									$COLOR_ARGS[] = constant('self::'. $prop_args[0]);
								}
							}

							break;

						//
						// Sets foreground to bold
						// 	<ansi fg="yellow" BOLD="true"></ansi>
						// 	<ansi fg="yellow" BOLDFG="true"></ansi>
						// 	<ansi fg="yellow" BOLD_FG="true"></ansi>
						//
						case 'BOLD':
						case 'BOLDFG':
						case 'BOLD_FG':
							if ( strtolower($prop_args[0]) != 'false' ) {
								$COLOR_ARGS[] = AnsiColor::BOLD;
							}
							break;

						//
						// Sets background to bold
						// 	<ansi fg="yellow" BOLDBG="true"></ansi>
						// 	<ansi fg="yellow" BOLD_BG="true"></ansi>
						//
						case 'BOLDBG':
						case 'BOLD_BG':
							if ( strtolower($prop_args[0]) != 'false' ) {
								$COLOR_ARGS[] = AnsiColor::BG_BOLD;
							}
							break;

						//
						// Underlines text
						//	<ansi underline="true">Underlined text</ansi>
						//
						case 'UNDERLINE':
							if ( strtolower($prop_args[0]) != 'false' ) {
								$COLOR_ARGS[] = AnsiColor::UNDERLINE;
							}
							break;

						//
						// Uses ansi blink function - not everyone supports
						//	<ansi blink="true">Underlined text</ansi>
						//
						case 'BLINK':
							if ( strtolower($prop_args[0]) != 'false' ) {
								$COLOR_ARGS[] = AnsiColor::BLINK;
							}
							break;

						//
						// Inverts all tags so that background becomes foreground and foreground becomes background
						//	<ansi invert="true"><ansi fg="yellow" bg="red">FG YELLOW BG RED INVERTED</ansi></ansi>
						//
						case 'INVERT':
							if ( strtolower($prop_args[0]) != 'false' ) {
								$COLOR_ARGS[] = AnsiColor::INVERT;
							}
							break;

						//
						// Pads contained text on the left
						//	<ansi pad_right="50">Text padded left</ansi>
						//
						case "PAD_LEFT":
							$tag_match[2] = self::pad($tag_match[2], $prop_args[0], ' ', STR_PAD_LEFT);
							break;

						//
						// Pads contained text on the right
						//	<ansi pad_left="50">Text padded left</ansi>
						//
						case "PAD_RIGHT":
							$tag_match[2] = self::pad($tag_match[2], $prop_args[0], ' ', STR_PAD_RIGHT);
							break;

						//
						// Centers text
						// Allows arguments comma seperated: {padding string},{left bookend},{right bookend}
						// 	<ansi center=" ,|">asdf</ansi>
						// Use "true" to just center default behavior
						// 	<ansi center="true">asdf</ansi>
						//
						case "CENTER":

							if ( count($prop_args) > 1 ) {

								if ( count($prop_args) > 2 ) {
									$prop_args[1] = array($prop_args[1], $prop_args[2]);
								}

							}
							else {

								$prop_args[1] = strtolower($prop_args[0]) != 'false' ? '' : $prop_args[0];
								$prop_args[0] = ' ';
							}

							$tag_match[2] = self::centerText($tag_match[2], $prop_args[0], $prop_args[1]);

							break;

						//
						// Outputs text to specific coordinates
						// 	<ansi pos="50,25">This is written to x25 and y50</ansi>
						//
						case "POS":
							if ( count($prop_args) == 2 ) {
								$XY = $prop_args;
							}
							break;

						//
						// Clears screen
						// 	<ansi clear="true"></ansi>
						// To also clear scrollback buffer:
						// 	<ansi clear="all"></ansi>
						//
						case "CLEAR":
							if ( strtolower($prop_args[0]) == 'all' ) {
								$clear_screen = 'all';
							}
							else {
								$clear_screen = true;
							}
							break;

						default:
							break;
					}

				}


				if ( $COLOR_ARGS ) {
					$new_text	= ANSIColor::color($tag_match[2], $COLOR_ARGS);
				}
				else {
					$new_text = $tag_match[2];
				}

				if ( !empty($XY) ) {

					// save cursor, move cursor, write text, move cursor back
					$new_text	= sprintf(	"\033[s\0337\033[%d;%dH%s\033[u\0338", $XY[1], $XY[0], $new_text);
					$XY			= null;

				}

				if ( $clear_screen ) {

					//
					// 0 = clear from cursor and beyond
					// 1 = clear from cursor and before
					// 2 = clear screen but it's still in scrollback
					// 3 = just delete everything in the scrollback buffer
					//

					if ( $clear_screen === 'all' ) {
						$new_text = "\033[3J" . $new_text;
					}

					$new_text = "\033[2J" . $new_text;

					$clear_screen = false;
				}

				$text = str_replace($tag_match[0], $new_text, $text);

			}

		}

		self::$total_parse_time +=	(microtime(true) - $parse_start);
		self::$total_parse_time -=	(self::$total_color_time - $color_time_before);

		return $text;
	}



	public static function color( $txt='' ) {

		self::$total_color_calls++;

		$color_start = microtime(true);

		if ( stripos($txt, '<ansi') !== false ) {
			$txt = ANSIColor::parse($txt);
		}

		$args	= func_get_args();
		$arg_ct	= func_num_args();

		$prefix = "\033[";
		$suffix = "\033[0m";

		$color_parts = array(ANSIColor::NORMAL => ANSIColor::NORMAL);

		$bold_bg = false;
		for( $i=1; $i < $arg_ct; $i++ ) {

			if ( is_array($args[$i]) ) {

				$tmpArgs	= $args[$i];
				$args[$i]	= $tmpArgs[0];

				for($j=1; $j<count($tmpArgs); $j++) {
					$args[] = $tmpArgs[$j];
				}

				$arg_ct = count($args);
			}

			if ( $args[$i] == ANSIColor::BG_BOLD ) {
				$bold_bg = true;
			}
		}

		for( $i=1; $i < $arg_ct; $i++ ) {

			if ( $bold_bg && $args[$i] >= ANSIColor::BG_BLACK  && $args[$i] <= ANSIColor::BG_WHITE ) {

				$color_parts[$args[$i]]	= $args[$i];// first add the normal color as a failover
				$args[$i]				+= 60; // increase to the possibly unsupported bolder option

			}

			if ( $args[$i] > 1000 && $args[$i] < 2000 ) {

				unset($color_parts[ANSIColor::NORMAL]);

				$color_parts[ANSIColor::BOLD] = ANSIColor::BOLD;

				$args[$i] -= 1000;
			}

			if (  $args[$i] != ANSIColor::BG_BOLD ) {
				$color_parts[$args[$i]] = $args[$i];
			}

		}

		$prefix .= implode(';', $color_parts) . 'm';

		// CHecks for other colors present in the strnig and wraps intelligently so that all non colored text still gets proper codes.
		preg_match_all("/\e\[.*?[m|\x08]/m", $txt, $matches, PREG_OFFSET_CAPTURE );

		if ( count($matches) && count($matches[0]) ) {

			$matches		= $matches[0];

			$last_pos		= 0;
			$parts			= array();
			for( $i=0; $i < count($matches); $i++ ) {

				$match			= $matches[$i];
				$color			= $match[0];
				$color_pos		= $match[1];

				$parts[]		= substr(	$txt,
					$last_pos,
					$color_pos - $last_pos);

				$parts[]		= substr(	$txt,
					$color_pos,
					self::mb_strlen($color));

				$last_pos		= $color_pos + self::mb_strlen($color);
			}

			if ( $last_pos < self::mb_strlen($txt) ) {

				$parts[]		= substr(	$txt,
					$last_pos);

			}

			$depth = 0;
			$txt = '';
			foreach( $parts as $p ) {

				if ( !self::mb_strlen($p) ) {
					continue;
				}

				if ( $p == $suffix ) {
					$depth--;
					$depth = max(0, $depth);
				}
				else if ( $p{0} ==  "\033" ) {
					$depth++;
				}

				if ( $depth > 0 ) {
					$txt .= $p;
				}
				else {
					$txt .= $prefix . $p . $suffix;
				}
			}

		}

		self::$total_color_time +=	(microtime(true) - $color_start);

		return $prefix	.
			$txt	.
			$suffix;
	}



	private static function centerText($text, $pad_text=' ', $bookend='', $width=0) {

		$pad	=	STR_PAD_BOTH;

		if ( stripos($text, '<ansi') !== false ) {
			$text = self::parse($text);
		}

		if ( $width == 0 ) {
			$width = ANSIColor::$screen_width;
		}

		$len = $width;

		if ( !empty($bookend) ) {

			if ( !is_array($bookend) ) {
				$bookend = array($bookend, $bookend);
			}

			$len -= ( self::strlen($bookend[0]) + self::strlen($bookend[1]) );


		}else {
			$bookend = array('', '');

		}

		// if a pad string is provided that's color wrapped, break out the colors and wrap the final left/right pad srings in it
		// This avoids over-saturation of the same color code being used over and over for a single character padding in a color
		$color_start	= '';
		$color_end		= '';

		preg_match_all("/\e\[.*?[m|\x08]/m", $pad_text, $matches );

		if ( count($matches) && count($matches[0]) ) {

			$color_start	= $matches[0][0];
			$color_end		= $matches[0][count($matches[0])-1];

			$pad_text		= substr($pad_text, 0, self::mb_strlen($pad_text) - self::mb_strlen($color_end));
			$pad_text		= substr($pad_text, self::mb_strlen($color_start));
		}


		$len		-=	self::strlen($text);

		$half_len	=	$len >> 1;

		return	$bookend[0] .
			$color_start .
			self::pad('',	$half_len,		$pad_text,	STR_PAD_LEFT) .
			$color_end .
			$text .
			$color_start .
			self::pad('',	$len-$half_len,	$pad_text,	STR_PAD_RIGHT) .
			$color_end .
			$bookend[1];

	}

	private static function pad($input, $length, $pad_string=' ', $pad_side=STR_PAD_RIGHT) {

		$real_length	= self::strlen($input);
		$raw_length		= self::mb_strlen($input);
		$difference		= $raw_length	-	$real_length;

		return str_pad($input, $length + $difference, $pad_string, $pad_side);

	}

	private static function mb_strlen( $text ) {

		if ( self::$mb_functions === true ) {
			return mb_strlen($text);
		}
		else if ( self::$mb_functions === false ) {
			return strlen($text);
		}
		else {
			self::$mb_functions = function_exists('mb_strlen');
		}

		return self::mb_strlen($text);
	}

	public static function getLogs() {
		return array(
			'ANSIColor::parse() timer'	=>	self::$total_parse_time,
			'ANSIColor::parse() calls'	=>	self::$total_parse_calls,
			'ANSIColor::color() timer'	=>	self::$total_color_time,
			'ANSIColor::color() calls'	=>	self::$total_color_calls,
		);
	}

}




/*
//
// The demonstration/example code below only runs if running this class as a stand-alone script
//
if ( basename($argv[0]) != 'ANSIColor.class.php' ) {
	return;
}


echo ANSIColor::parse('<ansi clear="true"></ansi><ansi fg="red">T<ansi bg="blue" bold="true" fg="yellow">e</ansi>xt</ansi>') . "\n";
exit;

$T_SPENT = ANSIColor::getLogs();

foreach ( $T_SPENT as $method=>$time ) {
	echo sprintf("%s:\t%s", $method, $time) . "\n";
}
*/
?>
