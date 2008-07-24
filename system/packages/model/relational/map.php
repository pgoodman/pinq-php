<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * through([string $model1[, string $model2[, ...]]]) -> array
 *
 * Define a through relationship. This is simply syntactic sugar for an
 * array that gives more semantic meaning to relationships.
 *
 * @author Peter Goodman
 */
function through() {
	return func_get_args();
}

/**
 * Class that stores all relations, mappings, and paths between models for a 
 * given data source.
 *
 * @author Peter Goodman
 */
class PinqModelRelationalMap implements InstantiablePackage {
	
	const DIRECT = 1,
	      INDIRECT = 2;
	
	protected $relations = array(),
	          $mappings = array(),
	          $paths = array(); // cached paths
	
	/**
	 * $r->registerModel(string $model_name) -> void
	 *
	 * Register a model with the relations class. This creates default empty
	 * relations, mappings, and paths for this model.
	 */
	public function registerModel($model_name) {
		
		$model_name = strtolower($model_name);
		
		$this->relations[$model_name] = array();
		$this->mappings[$model_name] = array();
		$this->paths[$model_name] = array();
	}
	
	/**
	 * $r->addRelation(string $from, string $to[, array $through]) -> void
	 *
	 * Add a relation between two models. If $through is not empty the path
	 * is indirect and goes through the intermediate models somehow.
	 */
	public function addRelation($from, $to, array $through = NULL) {
		
		// add the relationship
		$how = !empty($through) ? self::INDIRECT : self::DIRECT;
		$is_indirect = $how === self::INDIRECT;
		
		// add in the relationships
		$this->relations[$from][$to] = array($how, $through);
		$this->relations[$to][$from] = array(
			$how, 
			$is_indirect ? array_reverse($through) : NULL
		);
		
		// if we're going through other models, then this table is
		// directly related to the first model in the $through array.
		// we assume the same type of relationship.
		if($is_indirect) {
			
			// the first item in the $through might actually be indirect. if
			// it's already in the relations then we just won't set it. if it
			// isn't then we'll assume direct
			if(!isset($this->relations[$from][$through[0]])) {
				$this->relations[$from][$through[0]] = array(
					self::DIRECT, 
					NULL
				);
			}
		}
	}
	
	/**
	 * $r->addMapping(string $this_model, string $this_field,
	 *                string $that_model, string $that_field) -> void
	 *
	 * Add a mapping between the fields of one model and those of another.
	 * This also adds in a cached path between the two models and creates a
	 * direct relationship between them automatically.
	 *
	 * @see ModelRelation::addRelation(...), ModelRelation::addDirectPath(...)
	 */
	public function addMapping($this_model, $this_field, 
	                           $that_model, $that_field) {
		
		// model aliases are case insensitive
		$this_model = strtolower($this_model);
		$that_model = strtolower($that_model);
			
		// create the mapping between the property of a foreign model to a
		// property of this model. assumption: only one mapping from one
		// table to another.
		$this->mappings[$this_model][$that_model] = array(
			$that_field, 
			$this_field,
		);
		$this->mappings[$that_model][$this_model] = array(
			$this_field,
			$that_field, 
		);

		// add in a direct relationship. relations are used for figuring out
		// difficult paths later on
		$this->addRelation($this_model, $that_model, NULL);
		
		// add in the paths immediately
		$this->addDirectPath(
			$this_model, $this_field, 
			$that_model, $that_field
		);
	}
	
	/**
	 * $r->addDirectPath(string $this_model, string $this_field,
	 *                  string $that_model, string $that_field) -> void
	 *
	 * Create a direct simple path between two models.
	 *
	 * @internal
	 */
	protected function addDirectPath($this_model, $this_field, 
	                                 $that_model, $that_field) {
		
		$this->paths[$this_model][$that_model] = array(
			array($this_model, $this_field),
			array($that_model, $that_field),
		);
		$this->paths[$that_model][$this_model] = array(
			array($that_model, $that_field),
			array($this_model, $this_field),
		);
	}
	
	/**
	 * $r->getPath(string $from_alias, string $to_alias, PinqModelDictionary)
	 * -> array
	 *
	 * Given the external names of the starting and ending models, find a path
	 * of field-to-field mappings between them. If no path exists then an empty
	 * array is returned.
	 */
	public function getPath($from_alias, $to_alias, PinqModelDictionary $models) {

		$path = array();
		$current = $from_alias;		
		$relations = &$this->relations; // short-circuit :P
		$mappings = &$this->mappings;

		// yes, given the while loop condition this is a bit redundant, but
		// the while loop is variable, of course.
		
		// check if we've already found the path that we're about to compute
		if(isset($this->paths[$from_alias][$to_alias]))
			return $this->paths[$from_alias][$to_alias];
		
		// keep a queue of the next models to look at
		$next_models = new Queue; 
		$next_models->push($to_alias);
				
		// walk the path from $from_alias to $to_alias and build up a
		// descriptive array of how to link every model in the path. Follow
		// direct relations and crack open indirect ones.
		while(isset($relations[$current]) && !$next_models->isEmpty()) {
			
			// shift the next model to look at off the queue
			$next = $next_models->shift();
			
			if(empty($next))
				continue;
			
			// sort of a hack to make sure that they're loaded, this is the
			// only drawback of storing relations separately: when we want
			// to fill in the missing links between models, there's no guarantee
			// that any of the models being crossed have been loaded			
			$models[$current];
			$models[$next];
			
			// relationship cannot be satisfied
			if(!isset($relations[$current][$next]) || 
			   !isset($relations[$next][$current])) {

				// cache the failed attempt
				$this->paths[$from_alias][$to_alias] = array();
				return $this->paths[$to_alias][$from_alias] = array();
			}
			
			// backward relationship, we're figuring this out--regardless of
			// if we're supposed to or not :P
			if(!isset($relations[$current][$next])) {
				$how_to_relate = $relations[$next][$current];
				$how_to_relate[1] = array_reverse($how_to_relate[1]);
			
			// forward relationship
			} else
				$how_to_relate = $relations[$current][$next];
			
			// life is simple, we're dealing with a direct relationship.
			// Change $current to $next, and find out the new $next.
			if($how_to_relate[0] & self::DIRECT) {
												
				$next_key = $current_key = NULL;
				
				// now that we've established a direct relationship, we need
				// to figure out what maps one model to another. the mappings
				// could be stated in either model so we need to check both
				
				// the mapping is in this model
				if(isset($mappings[$current][$next]))
					list($next_key, $current_key) = $mappings[$current][$next];
				
				// the mapping is in the next model
				else if(isset($mappings[$next][$current]))
					list($current_key, $next_key) = $mappings[$next][$current];
								
				// a relationship could not be satisfied
				else {

					// cache the failed attempt
					$this->paths[$from_alias][$to_alias] = array();
					return $this->paths[$to_alias][$from_alias] = array();
				}
				
				// add the mapping to the path
				$path[] = array($current, $current_key);
				$path[] = array($next, $next_key);
				
				// cache the path for future requests
				$this->addDirectPath(
					$current, $current_key, 
					$next, $next_key
				);
				
				// switch the $current item to look at (for the next iteration)
				// to the $next item of this loop. this process can be easily
				// visualized as walking up stairs.
				$prev = $current;
				$current = $next;
				
			// life is slightly more complex, but not annoyingly so. Take off
			// what *should* be the $next model to look at and continue
			// without changing $current. This section is made slightly more
			// complex because it allows arbitrary jumps in through relations.
			// for example, this will solve the following through 
			// relationships incrementally:
			//
			// "a" relates to "d" indirectly through "c"
			// "b" relates to "c"
			// "c" relates directly to "d"
			//
			// because of these jumps we need to keep track of what we know
			// to happen later on in the path, and then fill in the gaps as
			// we go.
			} else {
				
				$old_nexts = $next_models->getArray();
				$next_models->clear();
				$next_models->extend($how_to_relate[1]);
				$next_models->push($next);
				$next_models->extend($old_nexts);
			}
		}

		// cache it for possible later use
		$this->path[$from_alias][$to_alias] = &$path;
		$this->path[$to_alias][$from_alias] = array_reverse($path);
		
		return $path;
	}
	
	/**
	 * $r->getRelationDependencies(array $aliases, array &$relations, PinqModelDictionary)
	 * -> array
	 *
	 * Return a graph of the dependencies for this query, that is, lay out the
	 * links made in the query such that the links will occur in the proper
	 * order.
	 * 
	 * @example The graph returned is structured and interpreted as such:
	 *     'post' => array(               // post depends on users and content
	 *         'users' => array(          // users depends on profiles
	 *             'profiles' => array(), // profiles has no dependencies
	 *         ),                         
	 *         'content' => array(),      // content has no dependencies
	 *     );
	 *
	 * @param array $aliases An array mapping aliases => model names in the query
	 * @param array $relations An array of from => (to, ...) relations in a query
	 * @internal
	 */
	public function getRelationDependencies(array &$aliases, 
	                                        array &$relations,
	                              PinqModelDictionary $models) {

		// to quickly access deeper areas in the graph, we will store
		// references to each place where these nodes show up in the graph
		// this is dependable based on the assumption that we've identified
		// all models uniquely through aliasing
		$entry_points = array();
		
		// we want to keep track of the trunks in the graph (think of the 
		// graph as a forest, where each tree is the dependencies for a model)
		$trunks = array();
		
		// temporary indexes for through queries
		$t = 1;
				
		// populate the entry nodes array. this is actually *more* complicated
		// than solving datasource dependencies because we need to sneak the
		// indirect relationships in.
		foreach($relations as $left => $rights) {
			
			$entry_points[$left] = NULL;
			$trunks[$left] = TRUE;
			
			// we need entry points for each alias. for most relations there
			// will only by one model in $rights.
			foreach($rights as $right) {
				
				// we could be doing a deep link, that is, implicity
				// going through other models to get from $left to $right.
				// if that's the case then we will add these dependencies
				// into the graph.
				$path = $this->getPath(
					$aliases[$left], 
					$aliases[$right],
					$models
				);
				
				// path will have no less than 2 arrays in it
				$count = count($path);
				
				if($count > 1) {
					
					// fix the aliases on the first and last models in the
					// path. this has to be done because the relation finds
					// unambiguous paths using model names, not aliases.
					$path[0][0] = $left;
					$path[$count-1][0] = $right;
					
					// remove the last model from being related to the
					// first.
					$key = array_search($right, $relations[$left]);
					unset($relations[$left][$key]);
					
					// go through the path and add the through relations into
					// the $relations array and give them entry points
					$last = $path[0][0];
					for($i = 1; $i < $count; $i += 2) {
						
						// because some of these are intermediate join tables
						// we don't wan't to assume that thay're not being
						// used elsewhere in the query so we alias them
						$name = ($i & 1) && $i < $count-1 ? 't'. $t++
						                                  : $path[$i][0];

						$relations[$last][] = $name;
						$entry_points[$name] = NULL;
						
						if(!isset($aliases[$name])) {
							
							// this is listed in the aliases
							if(isset($aliases[$path[$i][0]]))
								$aliases[$name] = $aliases[$path[$i][0]];
							
							// it isn't listed in the aliases, it's likely
							// beensubstituted in by the relations path, take
							// it as is.
							else
								$aliases[$name] = $path[$i][0];
						}
						
						// we need to keep the name (which could be an alias)
						// for the next iteration
						$last = $name;
					}
				}
				
				// set the default value for this entry point
				$entry_points[$right] = NULL;
			}
		}
								
		// go over the relations and build up the dependency graph. the graph
		// is structured as a multi-dimensional associative array. the keys on
		// the first level are the base things that we're trying to get that
		// need their dependencies satisfied. each of these are an associative
		// array, with keys being dependencies. this regresses as far as need
		// be.
		// for example:
		// 
		// 'post' => array(               // post depends on users and content
		//     'users' => array(          // users depends on profiles
		//         'profiles' => array(), // profiles has no dependencies
		//     ),                         
		//     'content' => array(),      // content has no dependencies
		// )
		//
		// this algorithm works for a very simple reason: all data sources
		// need to be uniquely aliased. that means that every key in the
		// dependency graph will be unique. given this, we build up the graph
		// using a flat array and then trim off items that don't belong in the
		// base level. the way this is done is by using keys as entry points
		// into deep parts of the graph. when we need to make something
		// dependent on another thing, we give it a reference to the entry
		// point, thus extending the graph deeper.
		foreach($relations as $left => $rights) {
			
			// ignore a left with no right relations
			if(empty($rights))
				continue;
			
			foreach($rights as $right) {
				
				// special case, we're linking to ourself, avoid recursion
				if($left == $right) {
					$entry_points[$left][$right] = array();
					continue;
				}
				
				// make sure we don't overwrite anything already done
				if(!isset($entry_points[$left][$right]))
					$entry_points[$left][$right] = array();
				
				// this hasn't been used
				if(empty($entry_points[$right]))
					$entry_points[$right] = &$entry_points[$left][$right];
				
				// we're adding an existing tree as a branch onto this item
				else 
					$entry_points[$left][$right] = &$entry_points[$right];
				
				// make sure we don't see the right item as a trunk
				unset($trunks[$right]);
			}
		}
		
		// take out any non-trunk items from the entry points array. these
		// are the final joins.
		return array_intersect_key($entry_points, $trunks);
	}
}
