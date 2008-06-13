<?php

require_once DIR_SYSTEM .'/handler.php';

/**
 * Class to handle the state of the parser. This is where the tag stack is
 * stored.
 * @author Peter Goodman
 * @internal
 */
final class TemplateTagStack extends Stack implements Printer {
	
	// the dummy name for the root node. it just needs to be something that
	// wouldn't be found in an actual template document
	const ROOT_NODE = '__root__';
	
	private $tag_handler, // handle tags
			$parse_func; // reference to TemplateParser::parse()
	
	/**
	 * Constructor, bring in tag info and template base dir.
	 */
	public function __construct(Handler &$tag_handler, array &$parse_func) {
		
		DEBUG_MODE && assert('call_user_func_array("method_exists", $parse_func)');
		
		$this->tag_handler = &$tag_handler;
		$this->parse_func = &$parse_func;
		$this->push(self::ROOT_NODE);
	}
	
	/**
	 * Push a tag onto the stack. Given the tag name as a string, we will
	 * request an object from the tag handler to push onto the tag stack.
	 */
	public function push($tag_name) {
		$tag_name = (string)$tag_name;
		
		// get the tag handler, and make sure we've got something
		if(NULL === ($tag = $this->tag_handler->get($tag_name)))
			throw new HandlerException("Unable to handle template tag with ". 
									   "name [{$tag_name}].");
		
		// make sure we're dealing with what we want to be.
		DEBUG_MODE && assert('$tag instanceof Compilable');
		
		// push the tag compiler onto the stack
		parent::push($tag);
		
		// give the new tag object back
		return $tag;
	}
	
	/**
	 * Pop a tag off of the stack.
	 */
	public function pop($expected = NULL) {
		
		// given that pop() is common to the stack interface, and that its a
		// public method, we'll make sure we're getting the right incoming
		// format
		$expected = (string)$expected;
		
		// expected comes in as a string, but we store tag handlers as objects
		// so cast the tag to a string, calling the objects __toString method.
		$tag = parent::pop();
		$actual = (string)$tag;
		
		// we expect to pop a certain tag off of the stack
		if(!empty($expected) && $actual != $expected)
			throw new ParserException("Unexpected tag [{$actual}] popped off ".
			                          "stack. Expected [{$expected}].");
		
		// add the output of this tag to the buffer of the parent tag
		$parent = $this->top();
		$parent->buffer($tag->compile());
		
		return $tag;
	}
	
	/**
	 * Return the current buffer.
	 */
	public function __toString() {
		try {
			return parent::top()->compile();
		} catch(StackException $e) {
			return "";
		}
	}
}

/**
 * A class to handle the root of any template.
 */
class TemplateNode extends Compilable {
	
	private $attributes = array();
	
	/**
	 * Set the tag attributes.
	 */
	public function setAttributes(array $attribs) {
		$this->attributes = array_merge($this->attributes, $attribs);
	}
	
	/**
	 * Get the tag name.
	 */
	public function __toString() {
		return '';
	}
}

/**
 * Class to handle parsing templates into an intermediate form. Compiling of
 * templates is done from the inside out. As closing template tags are found,
 * their output is added to the buffer of their parent tags. Parser states
 * are pushed onto the top of the stack and popped (put into the parent buffer).
 * @author Peter Goodman
 */
class TemplateStackParser implements Parser {
	
	private $tag_handler; // tag handlers
	
	/**
	 * Constructor, set the base directory for the template parser. Bring in
	 * the base directory where the templates are located, and a handler to
	 * deal with tag compilers.
	 */
	public function __construct(Handler &$tag_handler) {
		$this->tag_handler = &$tag_handler;
		$this->tag_handler->set(TemplateTagStack::ROOT_NODE, 
			                    new TemplateNode);
	}
	
	/**
	 * Parse a template.
	 */
	public function parse($input) {
		$parse_func = array(&$this, "parse");
		
		// create a new parser state. the parser states exist to sandbox
		// the parsing of individual files so that, if desired, we can parse
		// several files independently and then collapse them into eachother.
		$state = new TemplateTagStack($this->tag_handler, &$parse_func);
		
		// split the input into parts
		$parts = preg_split('~<(/?)([a-z0-9_]+):([a-z0-9_]+)((?: [^>]*)?)>~i',
							$input, -1, PREG_SPLIT_DELIM_CAPTURE);
		
		$num_parts = count($parts);
		
		// go over the split up parts of the template document
		for($i = 0; $i < $num_parts && isset($parts[$i+4]); ) {
			
			// get the parent tag and add whatever text is in-between this
			// tag and the previous (or the start of $input) to the parent's
			// buffer
			$parent = $state->top();
			$parent->buffer($parts[$i++]);
			
			// find out as much information as we can from the tag
			$is_closing_tag = trim($parts[$i++]) == '/';
			$namespace = trim($parts[$i++]);
			$tag_name = trim($parts[$i++]);
			$attribs = trim($parts[$i++]);
			
			// recompute the tag name, taking into account its possible
			// namespace
			$tag_name = $namespace . (!empty($tag_name) ? ':'. $tag_name : '');
			
			// is this a non-closing tag?
			$non_closing_tag = !$is_closing_tag && 
								strlen(rtrim($attribs, '/')) != strlen($attribs);
			
			// we're dealing with a closing tag
			if($is_closing_tag) {
				
				// close the tag and tell it that we expect to find the
				// $parent tag == $tag
				$state->pop($parent);
				
			// we're dealing with an opening tag	
			} else {
				$matches = array();
				$pattern = '~([a-z][a-z0-9_-]*)((?<=[:])[a-z0-9_-]+)?'.
						   '="(([^"]|((?<=\\\)"))*)"~ix';
				
				// the above pattern is smart enough to ignore a quote that is
				// preceded by a backslash; however, it doesn't differentiate
				// against the number of backslashes, so we will deal with
				// collapsing as many backslashes as we can before we parse out
				// the arguments.
				$attribs = preg_replace('~([\\\]{2}+)~x', '\\', $attribs);
				$attributes = array();
				
				if(preg_match_all($pattern, $attribs, $matches))
					$attributes = array_combine($matches[1], $matches[3]);
				
				// push the tag onto the stack
				$tag = $state->push($tag_name);
				$tag->setAttributes($attributes);
				
				// is this a non-closing tag? If so, pop it off the stack
				$non_closing_tag && $state->pop();
			}
		}
		
		return (string)$state;
	}
}

class GenericHandler extends Handler {
	
}

$content = '<list:default id="archive_posts">
<div class="message">
	There are no articles archived here.
</div>
</list:default>
<list:open id="archive_posts">
<div class="post">
	<ul class="archives">
		<list:each id="archive_posts" as="post">
		<li>
			<a href="{$post.permalink}" title="{$post.post_title}">{$post.post_title}</a>
			<span>{$post.date}</span>
		</li>
		</list:each>
	</ul>
</div>
</list:open>';

$parser = new TemplateParser(new Handler());
$parser->parse($content);
