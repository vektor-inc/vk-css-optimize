<?php
/**
 * CSS Simple Tree Shaking
 * 
 * Description: CSS tree shaking minify library
 * Version: 3.2.0
 * Author: enomoto@celtislab
 * Author URI: https://celtislab.net/
 * License: GPLv2 
 */

 // namesupace は変更しないと celtislab のプラグインなどと干渉して誤動作する可能性があるため変更.
 namespace VektorInc\VK_CSS_Optimize;

defined( 'ABSPATH' ) || exit;

class CSS_tree_shaking {
    
    private static $is_parse;
    private static $tag;
    private static $id;
    private static $class;
    private static $style;
    private static $type;
    private static $role;
    private static $attrlist;
    private static $jsaddlist;

	function __construct() {}

    //Remove CSS data including unused id / class / tag
    private static function tree_shaking($css) {
        $mincss = preg_replace_callback( '`(?<sel>[^{]+?)\{(?<pv>.*)\}(?<after>[^}]*$)`u', function($mstyle) {            
            $before = '';
            $sel    = $mstyle['sel'];                    
            if(false !== ($sep = strrpos($sel, ';'))){
                $before = substr($sel, 0, $sep + 1);
                $sel    = substr($sel, $sep + 1);
            }
            $pv     = $mstyle['pv'];
            $after  = $mstyle['after'];
            
            //Remove unused selectors
            $_sel = (strpos($sel, '(') !== false)? preg_replace_callback( '`\((((?>[^()]+)|(?R))*)\)`', function($rep){ return str_replace(',', "\t", $rep[0]); }, $sel) : $sel;
            array_map( function($s) use(&$sel){
                if(empty($s) || strpos($s, '@') !== false){
                    //Skip @ at-rule
                } else {
                    //Skip pseudo-classes that can describe selectors for simplification (determine by $_s and target $s when deleting)
                    //Except for :not, the evaluation does not exclude elements with a single element.
                    $_s = $s;
                    if(strpos($s, '(') !== false){
                        $_s = preg_replace_callback( '`(:not|:where|:is|:has)(\(((?>[^()]+)|(?2))*\))`', function($pseudo){
                                return ($pseudo[1] !== ':not' && strpos($pseudo[2], ',') === false)? preg_replace( '`(:not)(\(((?>[^()]+)|(?2))*\))`', '', $pseudo[0]) : '';
                            }, str_replace("\t", ',', $s) );
                            
                        //Skip nth- pseudo-elements such as :nth-child(odd) (to prevent the tag from recognizing what is inside the parentheses)
                        $_s = preg_replace_callback( '`:nth[^)]+\)`', function($pseudo){
                                return '';
                            }, $_s );
                    }

                    $offset = 0;
                    $maxlen = strlen($_s);
                    while($offset < $maxlen && preg_match( '`(?<id>#)(?<iname>[\w\-\\%]+)|(?<class>\.)(?<cname>[\w\-\\%]+)|(?<attr>\[)(?<atype>class|id|style|type|role)(?<mark>\^|\$|\*)?=(?<astr>.+?)\]|^(?<tag1>[\w\-\\%]+)|[,\s>\+~\(\)\]\|](?<tag2>[\w\-\\%]+)`u', $_s, $item, PREG_OFFSET_CAPTURE, $offset)){
                        if(!empty($item['id'][0])){
                            $name = $item['iname'][0];
                            if(!isset(self::$jsaddlist[$name]) && !isset(self::$id[$name])){
                                $sel = preg_replace( '`(' . preg_quote($s) . ')(,|$)`u', '$2', $sel, 1 );
                                break;
                            }                             
                        } elseif(!empty($item['class'][0])){
                            $name = $item['cname'][0];
                            if(!isset(self::$jsaddlist[$name]) && !isset(self::$class[$name])){
                                $sel = preg_replace( '`(' . preg_quote($s) . ')(,|$)`u', '$2', $sel, 1 );
                                break;
                            }
                        } elseif(!empty($item['tag1'][0]) || !empty($item['tag2'][0])){                            
                            $name = strtolower( (empty($item['tag1'][0]))? $item['tag2'][0] : $item['tag1'][0] );
                            if(!isset(self::$jsaddlist[$name]) && !isset(self::$tag[$name])){
                                $sel = preg_replace( '`(' . preg_quote($s) . ')(,|$)`u', '$2', $sel, 1 );
                                break;
                            }                            
                        } elseif(!empty($item['attr'][0])){
                            $before = $after  = '';
                            if($item['mark'][0] == '^'){
                                $before = $item['mark'][0];
                            } elseif($item['mark'][0] == '$'){
                                $after  = $item['mark'][0];
                            } elseif($item['mark'][0] != '*'){
                                $before = '^';
                                $after  = '$';
                            }
                            if ( strpos(self::$attrlist[ $item['atype'][0] ], $before. trim($item['astr'][0], ' \'"') . $after ) === false){
                                $sel = preg_replace( '`(' . preg_quote($s) . ')(,|$)`u', '$2', $sel, 1 );
                                break;
                            }                            
                        }
                        $offset = $item[0][1] + strlen($item[0][0]);
                    }
                }                    
            }, explode(',', $_sel));
            if(!empty($sel)){
                $sel = trim( preg_replace_callback( '`(,|\s){2,}`su', function($_s){ return $_s[1]; }, $sel ), ' ,');                  
            }
          
            if(empty($sel) || empty($pv)){
                $result = $before . $after;
            } elseif(strpos($sel, '@') !== false && preg_match("#@(\-[\w]+\-)?(keyframes|counter\-style)\s(.+)$#", $sel, $aid)) {
                self::$attrlist['atref'][$aid[3]] = $aid[2];                            
                $result = $before . $sel . '{'. $pv .'}' . $after;
            } elseif(strpos($pv, '{') !== false) {
                $nest = preg_replace_callback( '`([^{]*?)(\{((?>[^{}]+)|(?2))*\})`', function($_css){ return self::tree_shaking($_css[0]); }, $pv );                        
                if(empty($nest)){
                    $result = $before . $after;
                } else {
                    $result = $before . $sel . '{'. $nest .'}'  . $after;
                }
            } else {
                $result = $before . $sel . '{'. $pv .'}' . $after;
            }
            return $result;
        }, $css);
        return $mincss;
    }

    //Delete unused variable definitions (implemented after executing tree_shaking)
    //Note that if the CSS file is split, even the variable definitions used may be deleted.
    public static function tree_shaking4var($css) {
        $varlist = (preg_match_all( "/var\((\-\-[^\s\),]+?)[\s\),]/u", $css, $vmatches))? array_flip( $vmatches[1] ) : array();
        if(!empty(self::$attrlist['style'])){
            $inline  = (preg_match_all( "/var\((\-\-[^\s\),]+?)[\s\),]/u", self::$attrlist['style'], $vmatches))? array_flip( $vmatches[1] ) : array();
            $varlist = array_merge( $varlist, $inline);
        }
        return preg_replace_callback( '`(\-\-[\w\-]+?):.*?(url\([^\)]+?\).*?)?([;\}])`u', function($match) use($varlist) {
            if(!isset($varlist[ trim($match[1]) ])){
                $match[0] = ($match[3] === '}')? '}' : '';
            }
            return $match[0];
        }, $css);            
    }

    /*=============================================================
     * Simple minification that just removes comments, line breaks, whitespace, etc. in CSS
     */
    public static function simple_minify( $css ) {
        $css = preg_replace('`/\*[^*]*\*+([^/][^*]*\*+)*/`', '', $css );
        $css = preg_replace('`\s{2,}`su', ' ', str_replace(array("\r", "\n", "\t"), ' ', $css));
        return preg_replace_callback('`(\s(?<fs>[\{\}=,\)\/;>])|(?<rs>[\{\}:=,\(\/!;])\s)`su', function($_s) { return (!empty($_s['fs']))? $_s['fs'] : $_s['rs']; }, $css);
    }

    /*=============================================================
     * Remove CSS data including unused id / class / tag
     * 
     * $css         : CSS for tree shaking (simple_minified normalized CSS data)
     * $html        : HTML of the target page
     * $jsaddlist   : Register IDs and classes added by JS after DOM loading (excludes tree shaking)
     * $varminify   : Remove unused CSS variable definitions
     * $atrefminify : Remove unused referrer definitions such as @atrule keyframes 
     */
    public static function extended_minify($css, $html, $jsaddlist=array(), $varminify=false, $atrefminify=false) {

        self::$jsaddlist = (!empty($jsaddlist))? array_flip( $jsaddlist ) : array(); 
        
        //Extract tag, id, class from HTML
        if(empty(self::$is_parse)){
            self::$is_parse = true;
            self::$id    = array();
            self::$class = array();
            self::$style = array();
            self::$type  = array();
            self::$role  = array();
            self::$tag   = array_flip( array('html','head','body','title','style','meta','link','script','noscript') );        
            
            preg_replace_callback( '`<style(?<catrb>[^>]*?)>|<script(?<satrb>[^>]*?)>|<(?<tag>[\w\-]+)(?<tatrb>[^>]*?)>`s', function($item){
                $atrb = '';
                if(!empty($item['catrb'])) {
                    $atrb = trim($item['catrb']);
                } elseif(!empty($item['satrb'])) {
                    $atrb = trim($item['satrb']);
                } elseif(!empty($item['tag'])){
                    $atrb = trim($item['tatrb']);
                    self::$tag[strtolower($item['tag'])] = 1;
                }
                if(!empty($atrb)){
                    preg_replace_callback( '`(id|class|style|type|role)\s?=\s?([\'"])(.+?)\2`u', function($_atrb){
                        $sep = ($_atrb[1] === 'style')? ';' : ' ';
                        array_map( function($_s) use($_atrb) { self::${$_atrb[1]}[trim($_s)] = 1; }, explode($sep, trim($_atrb[3], ' ;')));
                        return $_atrb[0];
                    }, $atrb );
                }
                return $item[0];                
            }, $html );
            //for id / css / inline style / type /role attribute selector matching
            self::$attrlist['id']    = '^' . implode("$^", array_keys(self::$id)) . '$';
            self::$attrlist['class'] = '^' . implode("$^", array_keys(self::$class)) . '$';            
            self::$attrlist['style'] = '^' . implode("$^", array_keys(self::$style)) . '$';            
            self::$attrlist['type']  = '^' . implode("$^", array_keys(self::$type)) . '$';            
            self::$attrlist['role']  = '^' . implode("$^", array_keys(self::$role)) . '$';            
        }
        
        if( substr_count($css, '{') === substr_count($css, '}') ){
            $css = preg_replace_callback( '`([^{]*?)(\{((?>[^{}]+)|(?2))*\})`', function($_css){ return self::tree_shaking($_css[0]); }, $css );            
            if($atrefminify && !empty(self::$attrlist['atref'])){
                foreach(self::$attrlist['atref'] as $aid => $type){
                    if(!preg_match("`:$aid(\s|;)`", $css)){
                        $css = preg_replace("`@((\-[\w]+\-)?$type\s$aid)(\{((?>[^{}]+)|(?3))*\})`", '', $css, 1);
                    }
                }                
            }
            if($varminify){
                $css = self::tree_shaking4var( $css );
            }
        }
        return $css;
    }
}