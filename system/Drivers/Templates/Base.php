<?php namespace Drivers\Templates;

/**
 *This class handles template processing
 *@author Geoffrey Oliver <geoffrey.oliver2@gmail.com>
 *@copyright 2015 - 2020 Geoffrey Oliver
 *@category Core
 *@package Core\Drivers\Templates
 *@link https://github.com/gliver-mvc/gliver
 *@license http://opensource.org/licenses/MIT MIT License
 *@version 1.0.1
 */

use Drivers\Utilities\StringUtility;
use Drivers\Utilities\ArrayUtility;

class  Base {

	use Map;

	/**
	 *@var 
	 *@readwrite
	 */
	protected $getImplementation;

	/**
	 *@var
	 *@readwrite
	 */
	protected $header = "if (is_array(\$_data) && sizeof(\$_data)) extract(\$_data); \$_text = array(); ";

	/**
	 *@var
	 *@readwrite
	 */
	protected $footer = "return implode(\$_text);";

	/**
	 *@var
	 *@read
	 */
	protected $code;

	/**
	 *@var
	 *@read
	 */
	protected $function;

	/**
	 *This method defines the exception for implementation
	 *
	 *@param string $method The method name to pass for this exception
	 *@return object Object instance of this exception class
	 */
	public function getExceptionForImplementation($method)
	{
		return new Exception\Implementation("{$method} method not implemented");

	}

	/**
	 *This method is used for any of the statements, if they have a specific argument format such as for,foreach...
	 *
	 *It returns the bits between the {...} characters in a neat associative array
	 *@param string $source
	 *@param string $expression
	 *@return array Characters in between {...} in associative array
	 */
	protected function arguments($source, $expression)
	{

		$args = $this->getArray($expression, array(
				$expression => array(
					'opener' => "{",
					'closer' => "}"
				)
			)
		);

		$tags = $args['tags'];

		$arguments = array();

		$sanitized = StringUtility::sanitize($expression, "()[],.<>*$@");

		foreach ($tags as $i => $tag) 
		{
			$sanitized = str_replace($tag, "(.*)", $sanitized);

			$tags[$i] = str_replace(array("{", "}"), "", $tag);

		}

		if(preg_match("#{$sanitized}#", $source, $matches))
		{

			foreach ($tags as $i => $tag) 
			{

				$arguments[$tags] = $matches[$i+1];

			}

		}

		return $arguments;

	}

	/**
	 *Generates a $node array which contains a bit of metadata about the tag.
	 *This method calls the implementation's match method, which tells the parser if the chunk of template
	 *we are parsing is a tag or a plain string. The match method will return false for a  nonmatch. It then extracts
	 *all the bits between the opener and closer strings we specified in the implementation. For example, for {if $check}
	 *the match() method will extract the tag 'if' the rest of the tag data ($check), and whether or not it is a closing tag.
	 *
	 *@param string $source The source string to parse
	 *@return 
	 */
	protected function tag($source)
	{

		$tag = null;

		$arguments = array();

		$match = $this->getImplementation->match($source);

		if($match == null)
		{
			return false;

		}

		$delimiter = $match['delimiter'];

		$type = $match['type'];

		$start = strlen($type['opener']);
		$end = strpos($source, $type['closer']);
		$extract = substr($source, $start, $end - $start);

		if(isset($type['tags']))
		{

			$tags = implode("|", array_keys($type['tags']));

			$regex = "#^(/){0,1}({$tags})\s*(.*)$#";

			if ( ! preg_match($regex, $extract, $matches)) 
			{

				return false;

			}

			$tag = $matches[2];

			$extract = $matches[3];

			$closer = !!$matches[1];

		}

		if($tag && $closer)
		{

			return array(
				'tag' => $tag,
				'delimiter' => $delimiter,
				'closer' => true,
				'source' => false,
				'arguments' => false,
				'isolated' => $type['tags'][$tag]['isolated']

			);

		}

		if(isset($type['arguments']))
		{

			$arguments = $this->arguments($extract, $type['arguments']);

		}

		elseif ($tag && isset($type['tags'][$tag]['arguments'])) 
		{

			$arguments = $this->arguments($extract, $type['tags'][$tag]['arguments']);

		}

		return array(
			'tag' => $tag,
			'delimiter' => $delimiter,
			'closer' => false,
			'source' => $extract,
			'arguments' => $arguments,
			'isolated' => ( ! empty($type['tags']) ? $type['tags'][$tag]['isolated'] : false)

		);


	}

	/**
	 *This method takes a node array and determines the correct handler method to execute
	 *
	 *@param array $node
	 *@return object Handler method instance
	 */
	protected function handler($node)
	{
		//if the node does not have a delimiter, return null
		if ( empty($node['delimiter'])) 
		{
			//return null value
			return null;

		}

		//return method if 'tag' index is present
		if ( ! empty($node['tag'])) 
		{
			//return the appropriate method
			return $this->map[$node['delimiter']]['tags'][$node['tag']]['handler'];

		}

		//else return this methos
		return $this->map[$node['delimiter']]['handler'];

	}

	/**
	 *This method executes the corrrect handler method
	 *
	 *@param array $node The node to use for checking for handler
	 *@param string $content The php file content to parse
	 *@return object Object instance of this class implementation
	 *@throws Exception\Implementation of there was problem executing the statement's handler
	 */
	public function handle($node, $source)
	{
		try{

			//get the handler method
			$handler = $this->handler($node);

			//return instance of this object
			return call_user_func_array(array($this, $handler), array($node, $content));

		}
		catch(\Exception $e){

			throw new Core\Drivers\Templates\Exception();

		}

	}

	/**
	 *This method evaluates a source string to determine if it matches a tag or statement
	 *
	 *@param string $string The source string to search
	 *@return
	 */
	public function match($source)
	{
		//set type to null
		$type = null;

		//set the delimiter to null
		$delimiter = null;

		foreach ($this->map as $delimiterB => $typeB) 
		{
			//set the variables if not set yet
			if ( ! $delimiter || StringUtility::indexOf($source, $type['opener']) == -1) 
			{
				//set the delimiter
				$delimiter = $delimiterB;

				//set the type
				$type = $typeB;

			}

			//get the string position
			$indexOf = StringUtility::indexOf($source, $typeB['opener']);

			//process if substring was found
			if ($indexOf > -1) 
			{
				//set new values once done with the previous substring
				if (StringUtility::indexOf($source, $type['opener']) > $indexOf) 
				{
					//set new delimiter
					$delimiter = $delimiterB;

					//set the new type
					$type = $typeB;

				}

			}

		}

		//if type is still null, then return null
		if ($type == null) 
		{
			//return null
			return null; 

		}

		//else return a multidimensional array with delimiter and type
		return array(
				'type' => $type,
				'delimiter' => $delimiter
			);

	}


	/**
	 *This method deconstructs the source string into array of tags, text and a combination of all.
	 *
	 *@param string $source The source content to parse
	 *@return array Array containing all the deconstructed parts in one multidimensional array
	 */
	protected function getArray($source)
	{
		//define the parts array
		$parts = array();

		//define the tags array
		$tags = array();

		//define the all array 
		$all = array();

		//set the type to null
		$type = null;

		//set the delimiter to null
		$delimiter = null;

		//loop through the source string performing actions
		while ($source) 
		{
			//search for a tag match
			$match = $this->getImplementation->match($source);

			//get the type
			$type = $match['type'];

			//get the delimiter
			$delimiter = $match['delimiter'];

			//get the opener string position
			$opener = strpos($source, $type['opener']);

			//get the closer string position
			$closer = strpos($source, $type['closer']) + strlen($type['closer']);

			//check if the opener was found
			if( $opener != false )
			{
				//get the string before the opening tags
				$parts[] = substr($source, 0, $opener); 

				//get the string from opening and closing tags
				$tags[] = substr($source, $opener, $closer - $opener);

				//remove part of source that has already been processed
				$source = substr($source, $closer);

			}
			//no valid template tags were found, take the whole $source string to be part
			else
			{
				//assign whole string to parts
				$parts[] = $source;

				//set the source to empty
				$source = '';

			}

		}

		//start processing the parts collected
		foreach ($parts as $i => $part) 
		{
			//add the parts together
			$all[] = $part;

			//check if tag with this index is present
			if( isset($tags[$i]) )
			{
				//add the tags to array
				$all[] = $tags[$i];

			}

		}

		//clean the tags, parts and full source string array and return
		return array(
				'text' => ArrayUtility::clean($parts),
				'tags' => ArrayUtility::clean($tags),
				'all' => ArrayUtility::clean($all)
			);

	}

	/**
	 *This method loops through the array of template segments generated by the array() method.
	 *It organizes them into a hierachichal structure.Plain text nodes are simply assigned as-is to
	 *the tree, while additional metadata is generated and assigned with the tags.
	 *
	 *@param array $array The deconstructed source string into array
	 *@return array The composed hierachichal structure of tags
	 */
	protected function tree($array)
	{
		//define the root array
		$root = array(
			'children' => array()
		);

		$current  = & $root;

		//loop through the array of string segments
		foreach ($array as $i => $node) 
		{
			//check if node has template tag in it
			$result = $this->tag($node);

			//process if a valid tag was found
			if ($result) 
			{
				//get the tag itself
				$tag = isset($result['tags']) ? $result['tags'] : '';

				//get the arguments for this tag
				$arguments = isset($result['arguments']) ? $result['arguments'] : '';

				//if there was a valid tag, process
				if ($tag) 
				{
					//check the type of tag
					if ( ! $result['closer']) 
					{
						//get the last tag in this hierachy
						$last = ArrayUtility::last($current['children']);

						//check if this tag is isolated type
						if ($result['isolated'] && is_string($last)) 
						{
							//remove this last item from the array
							array_pop($current['children']);

						}

						//populate metadata for this tag
						$current['children'][] = array(

								'index' => $i,
								'parent' => &$current,
								'children' => array(),
								'raw' => $result['source'],
								'tag' => $tag,
								'arguments' => $arguments,
								'delimiter' => $result['delimiter'],
								'number' => sizeof($current['children'])
						);

						//update the $current variable
						$current = & $current['children'][sizeof($current['children']) - 1];

					}

					//if the tag is not a closure
					else if(isset($current['tag']) && $result['tag'] == $current['tag'])
					{
						//get the start index
						$start = $current['index'] + 1;

						//get the lenght
						$length = $i - $start;

						//get the current source
						$current['source'] = implode(array_slice($array, $start, $length));

						//get the current parent
						$current = & $current['parent'];

					}

				}

				//if tag is empty
				else
				{
					//add to current children
					$current['children'][] = array(

							'index' => $i,
							'parent' => &$current,
							'children' => array(),
							'raw' => $result['source'],
							'tag' => $tag,
							'arguments' => $arguments,
							'delimiter' => $result['delimiter'],
							'number' => sizeof($current['children'])

					);

				}

			}

			//not a valid template tag
			else
			{
				//add to the children array
				$current['children'][] = $node;

			}

		}

		//return the resultant root/current array
		return $root;

	}

	/**
	 *This method converts a template tree provided into a PHP function
	 *
	 *@param array $tree The template tree to be parsed
	 *@return string Imploded string representation of the full $content array in the form of a function body
	 */
	protected function script($tree)
	{
		//define array to carry contain content to return
		$content = array();

		//chekc if tree is string
		if (is_string($tree)) 
		{
			//escape special characters by adding slashes
			$tree = addslashes($tree);

			//return as string
			return "\$_text[]=\"{$tree}\";";

		}

		//process if the tree has children
		if (sizeof($tree['children']) > 0) 
		{
			//loop through the children adding content
			foreach ($tree['children'] as $child) 
			{
				//add the content to content array
				$content[] = $this->script($child);

			}

		}

		//process if tree has parent
		if(isset($tree['parent']))
		{
			//return equivalent
			return $this->getImplementation->handle($tree, implode($content));

		}

		//return the resultant content array
		return implode($content);

	}

	/**
	 *This public method turns a template into a function
	 *
	 *@param string $template The view template file contents
	 *@return $this obejct function instance
	 */

	public function parse($template, $objectInstance)
	{
		$this->getImplementation = $objectInstance;

		//check for this object instance
		if ( ! is_a($this->getImplementation, 'Core\Drivers\Templates\Implementation')) 
		{
			//throw error
			throw new Exception("The object passed is not an instance of Templates\Implementation Class");

		}

		//turn string into array
		$array  = $this->getArray($template);


		//get tree structure from array
		$tree = $this->tree($array['all']);

		echo "<pre>";
		print_r($array);exit();

		//assign this return value  to code var
		$this->code = $this->header . $this->script($tree) . $this->footer;

		//call the function instance
		$this->function = function($_data) {$this->code;};

		//return function representation
		return $this;

	}

	/**
	 *The method processes the template in terms of array of input data
	 *
	 *@param array $data
	 *@return 
	 */
	public function process($data = array())
	{

		//throw exception if this function has not been set
		if ( $this->function == null) 
		{
			//throw exception
			throw new Exception("The callback function to process this request has not been defined!");

		}

		try {

			//get the function data into a var
			$function = $this->function;

			//return the data after processing by function
			echo $function($data);

		}
		catch(Core\Drivers\Templates\Exception  $error){

			$error->show();
		}

	}

}

