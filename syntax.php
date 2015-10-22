<?php
/**
 * Filelist Plugin: Lists files matching a given glob pattern.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Frank Schiebel <frank@ua25.de>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/confutils.php');
require_once(DOKU_INC.'inc/pageutils.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_stafflist extends DokuWiki_Syntax_Plugin {

    var $mediadir;

    function syntax_plugin_stafflist() {
        global $conf;
        $basedir = $conf['savedir'];
        if (!$this->_path_is_absolute($basedir)) {
            $basedir = DOKU_INC . '/' . $basedir;
        }
        $this->mediadir = $this->_win_path_convert($this->_realpath($basedir.'/media').'/');
    }

    /**
     * return some info
     */
    function getInfo() {
        return array(
            'author' => 'Frank Schiebel',
            'email'  => 'frank@ua25.de',
            'date'   => '2015-10-22',
            'name'   => 'Stafflist Plugin',
            'desc'   => 'Shows a staff list created from a CSV file',
            'url'    => 'http://www.openschulportfolio.de',
        );
    }

    function getType(){ return 'substition'; }
    function getPType(){ return 'block'; }
    function getSort(){ return 222; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{stafflist>.+?\}\}',$mode,'plugin_stafflist');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler) {

        // do not allow the syntax in comments
        if (!$this->getConf('allow_in_comments') && isset($_REQUEST['comment']))
        return false;

        $match = substr($match, 2, -2);
        list($type, $match) = split('>', $match, 2);
        list($staffcsv, $flags) = split('&', $match, 2);

        $flags = split('&', $flags);
        $params = array(
            'showfields' => "",
        );

        foreach($flags as $flag) {
            list($name, $value) = split('=', $flag);
            $params[trim($name)] = trim($value);
        }

        return array($type, $staffcsv, $params, $title);
    }

    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        global $conf;

        // disable caching
        $renderer->info['cache'] = false;

        list($type, $staffcsv, $params, $title) = $data;
        if ($mode == 'xhtml') {

            $staffcsv = $this->_realpath($this->mediadir . str_replace(':', '/', $staffcsv));
            $result = $this->_create_stafflist($staffcsv, $params);
            $renderer->doc .= $result;
	    
        }
        return false;
    }

    //~~ Render functions

    //~~ Filelist functions

    /**
     * Creates the stafflist based on the given glob-pattern and
     * sorting and ordering parameters.
     *
     * @param $pattern the pattern
     * @param $params the parameters of the stafflist command
     * @return a stafflist data structure containing the found files and base-
     *         and webdir
     */
    function _create_stafflist($staffcsv, $params) {
        global $conf;
        global $ID;

	$showfields = $params["showfields"];;
        $show_fields_array = explode(",",$showfields);
	
	$delimiter = $this->getConf('delimiter');
	$cleancsvfile = $this->getConf('cleancsvfile');
        $cleancsvfile = dirname($staffcsv) . "/" . cleanID($cleancsvfile);

	if ( file_exists ($staffcsv) ) {
		# produce clean version 
		$this->_clean_csv($staffcsv);
	}

	if ( ! file_exists ($cleancsvfile) ) {
		return "No valid clean staffcsvfile found!";
	} 
	
	$handle = fopen($cleancsvfile, "r");
        # read first line
        if ( ($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            foreach ( $show_fields_array as $field ) {
		$keys_to_show[$field] = array_search($field, $data);
            }
        }
	
	fclose($handle);
	
	$handle = fopen($cleancsvfile, "r");

	
	$current_row = 0;
	$html = "<table class=\"stafflist\">";

    	while ( ($data = fgetcsv($handle, 0, $delimiter)) !== FALSE ) {
            $num = count($data);
	    # skip first line (field names)
	    if ( $current_row == 0) {
		if ( $this->getConf('printtableheader') == 1 ) {
                    $html .= "<tr>\n";
		    foreach ( $show_fields_array as $field ) {
			    $html .= "  <th>" . $field . "</th>\n";
		    }
		    $html .= "</tr>\n";
		}
		$current_row++;
		continue;
	    }

            $html .= "<tr>\n";
            foreach ( $show_fields_array as $field ) {
		$html .= "   <td>" . $data[$keys_to_show[$field]] . "</td>\n";
	    }
	    $html .= "</tr>\n";
	}
	$html .= "</table>";
        fclose($handle);
	
	return $html;
    }

    function _clean_csv($staffcsv) {
        global $conf;
        global $ID;

	$cleanfields = $this->getConf('cleanfields');
	$delimiter = $this->getConf('delimiter');
	$cleancsvfile = $this->getConf('cleancsvfile');
        $cleancsvfile = dirname($staffcsv) . "/" . cleanID($cleancsvfile);

        $clean_fields_array = explode(",",$cleanfields);
        $keys_to_show = array();

	$handle = fopen($staffcsv, "r");

        # read first line
        if ( ($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            foreach ( $clean_fields_array as $field ) {
		$keys_to_show[$field] = array_search($field, $data);
            }
        }
        
	fclose($handle);
	
	$handle = fopen($staffcsv, "r");

	$current_row = 0;
    	while ( ($data = fgetcsv($handle, 0, $delimiter)) !== FALSE ) {
            $num = count($data);
	    
	    # skip first line (field names)
	    if ( $current_row == 0) {
		$output= "";
            	foreach ( $clean_fields_array as $field ) {
			if ( $output == "" ) {
				$output .= $field;
			} else {
			        $output .= ",";
				$output .= $field;
			}
            	}
		$output .= "\n";
		$current_row++;
		continue;
	    }

	    $rowstarted = 0;
            foreach ( $clean_fields_array as $field ) {
		if ( $rowstarted == 0 ) {
			$output .= "\"" . $data[$keys_to_show[$field]] . "\"";
			$rowstarted = 1;
		} else {
			$output .= ",";
			$output .= "\"" . $data[$keys_to_show[$field]] . "\"";
            	}
	    }
	    $output .= "\n";
	    $current_row++;
	}
	$fp = fopen("$cleancsvfile", 'w');
	fwrite($fp, $output);
	fclose($fp);
        fclose($handle);
	# delete staffcsv (data protection)
	#unlink($staffcsv);
    }

    
    /**
     * Converts backslashs in paths to slashs.
     *
     * @param $path the path to convert
     * @return the converted path
     */
    function _win_path_convert($path) {
        return str_replace('\\', '/', trim($path));
    }


    /**
     * Determines whether a given path is absolute or relative.
     * On windows plattforms, it does so by checking whether the second character
     * of the path is a :, on all other plattforms it checks for a / as the
     * first character.
     *
     * @param $path the path to check
     * @return true if path is absolute, false otherwise
     */
    function _path_is_absolute($path) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return ($path[1] == ':');
        } else {
            return ($path[0] == '/');
        }
    }
    
    /**
     * Canonicalizes a given path. A bit like realpath, but without the resolving of symlinks.
     *
     * @author anonymous
     * @see <http://www.php.net/manual/en/function.realpath.php#73563>
     */
    function _realpath($path) {
        $path=explode('/', $path);
        $output=array();
        for ($i=0; $i<sizeof($path); $i++) {
            if (('' == $path[$i] && $i > 0) || '.' == $path[$i]) continue;
            if ('..' == $path[$i] && $i > 0 && '..' != $output[sizeof($output) - 1]) {
                array_pop($output);
                continue;
            }
            array_push($output, $path[$i]);
        }
        return implode('/', $output);
    }


}

// vim:ts=4:sw=4:et:enc=utf8:
