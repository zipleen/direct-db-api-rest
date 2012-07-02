<?php 

class utils{
	private static $charset = "iso-8859-1";
	private static $func = "";
	/**
	 * obter o tempo, em microsegundos
	 *
	 * @return int
	 */
	public static function getTime() {
		$time = microtime();
		$time = explode(' ', $time);
		$time = $time[1] + $time[0];
		$start = $time;
		return $start;
	}
	
	/**
	 * Verifica se um campo no $_GET existe
	 *
	 * @param $key
	 * @return bool
	 */
	public static function checkUrlGet($key){
		return isset($_GET[$key]);
	}
	/**
	 * verifica se uma string eh um numero
	 *
	 * @param string $nr
	 * @return bool
	 */
	public static function is_intval($nr)
	{
		//$nr = filter_var($nr, FILTER_SANITIZE_NUMBER_INT);
		return ((preg_match("/^[0-9]+$/", $nr) && $nr > 0 && trim($nr)!='')? true : false);
	}
	public static function intcast($a)
	{
		$a = filter_var($a, FILTER_SANITIZE_NUMBER_INT);
		return ((utils::is_intval($a)) ? $a : 0);
	}
	
	/**
	 * devolve texto que esta entre 2 "tags" - tags podem ser o que quiserem
	 * 
	 * Imaginemos:
	 *  <code>
	 *  $b = "bla (ola)";
	 *  
	 *  $entre = utils::getBetweenText($b, " (", ")" );
	 *  print_r($entre);
	 *  </code>
	 *  Isto vai dar:
	 *  Array( [0] => ola )
	 * 
	 * @param string $html
	 * @param string $tag_before
	 * @param string $tag_after
	 */
	public static function getBetweenText( $html, $tag_before, $tag_after ) {  
		$tag_before = preg_quote($tag_before);
		$tag_after = preg_quote($tag_after);
		$match = '';
		preg_match_all("/$tag_before(.*?)$tag_after/uis", $html, $match);
		return $match[1];
	}
	
	/**
	 * Obter um campo _GET
	 *
	 * @param unknown_type $key
	 * @param unknown_type $default
	 * @return unknown_type
	 */
	public static function httpGET($key, $default=''){
		if(isset($_GET[$key]))
			return $_GET[$key];
		else return $default;
	}
	/**
	 * Obter um campo do _POST
	 *
	 * @param unknown_type $key
	 * @param unknown_type $default
	 * @return unknown_type
	 */
	public static function httpPOST($key, $default=''){
		if(isset($_POST[$key]))
			return $_POST[$key];
		else return $default;
	}
	/**
	 * Obter um campo do _REQUEST
	 *
	 * @param unknown_type $key
	 * @param unknown_type $default
	 * @return unknown_type
	 */
	public static function httpREQUEST($key, $default=''){
		if(isset($_REQUEST[$key]))
			return $_REQUEST[$key];
		else return $default;
	}
	/**
	 * Obter um campo do _REQUEST
	 *
	 * @param unknown_type $key
	 * @param unknown_type $default
	 * @return unknown_type
	 */
	public static function httpREQUESTa($key, $key2, $default=''){
		if(isset($_REQUEST[$key]) && isSet($_REQUEST[$key][$key2]))
			return $_REQUEST[$key][$key2];
		else return $default;
	}
	
	public static function serialize($var)
	{
		return serialize($var);
	}
	
	public static function unserialize($var)
	{
		$var = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $var );
		return utils::strpos($var,"{")!==false? unserialize($var) : array();
	}
	
	/**
	 * problema do htmlentities: necessita de saber que a string eh utf8
	 *
	 * @param string $input
	 * @return string
	 */
	public static function htmlentities($input)
	{
		return htmlentities($input, ENT_QUOTES, utils::$charset);
	}
	
	public static function html_entity_decode($input)
	{
		return html_entity_decode($input, ENT_QUOTES, utils::$charset);
	}
	
	/**
	 * tentativa de tirar todo o tipo de XSS attacks (tipo javascript stuff e html stuff) de coisas introduzidas...
	 * o strip_tags nao deve funcionar totalmente, acho que eh preciso uma preg_match melhor...
	 *
	 * @param $html
	 * @return unknown_type
	 */
	public static function safeText($html)
	{
		if(is_array($html))
		{
			$html_outro = array();
			foreach($html as $bb=>$cc)
			{
				$html_outro[strip_tags($bb)] = strip_tags($cc);
			}
			$html = $html_outro;
			unset($html_outro);
			return $html;
		}
		return strip_tags($html);
	}
	
	/**
	 * substr de php
	 * <code>
	 * $rest = substr("abcdef", 0, -1);  // returns "abcde"
	 * $rest = substr("abcdef", 2, -1);  // returns "cde"
	 * $rest = substr("abcdef", 4, -4);  // returns ""
	 * $rest = substr("abcdef", -3, -1); // returns "de"
	 * </code>
	 *
	 * @param unknown_type $str
	 * @param unknown_type $start
	 * @param unknown_type $length
	 * @return unknown_type
	 */
	public static function substr($str , $start , $length = null)
	{
		$f = utils::$func."substr";
		if($length==null)
			return $f($str,$start);
		else
			return $f($str,$start,$length);
	}
	
	public static function strlen($str)
	{
		$f = utils::$func."strlen";
		return $f($str);
	}
	
	public static function strtolower($str)
	{
		$f = utils::$func."strtolower";
		return $f($str);
	}
	
	public static function strtoupper($str)
	{
		$f = utils::$func."strtoupper";
		return $f($str);
	}
	
	public static function strstr($str, $needle)
	{
		$f = utils::$func."strstr";
		return $f($str,$needle);
	}
	
	public static function strpos($haystack, $needle, $offset = null)
	{
		$f = utils::$func."strpos";
		if($offset==null)
			return $f($haystack,$needle);
		else
			return $f($haystack,$needle,$offset);
	}
	public static function stripos($haystack, $needle, $offset = null)
	{
		$f = utils::$func."stripos";
		if($offset==null)
			return $f($haystack,$needle);
		else
			return $f($haystack,$needle,$offset);
	}
	public static function strripos($haystack, $needle, $offset = null)
	{
		$f = utils::$func."stripos";
		if($offset==null)
			return $f($haystack,$needle);
		else
			return $f($haystack,$needle,$offset);
	}
	public static function strrpos($haystack, $needle, $offset = null)
	{
		$f = utils::$func."strrpos";
		if($offset==null)
			return $f($haystack,$needle);
		else
			return $f($haystack,$needle,$offset);
	}
}
?>