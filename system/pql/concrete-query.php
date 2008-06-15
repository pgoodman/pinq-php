<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * And interface for something to build a "concrete" thing, such as an SQL
 * statement, out of an abstract query. Amusingly, this class is abstract.
 * @author Peter Goodman
 */
abstract class ConcreteQuery {
	
	// query types
	const SELECT = 1,
	      INSERT = 2,
	      UPDATE = 4,
	      DELETE = 8;
	
	static protected $cache = array();
	
	/**
	 * See if a compiled query is already in the cache.
	 */
	static protected function getCachedQuery(AbstractQuery $query) {
		if(isset(self::$cache[$query->id]))
			return self::$cache[$query->id];
		
		return NULL;
	}
	
	/**
	 * Cache a compiled query.
	 */
	static protected function cacheQuery(AbstractQuery $query, $stmt) {
		self::$cache[$query->id] = $stmt;
	}
	
	/**
	 * Return a graph of the dependencies for this query, that is, lay out the
	 * links made in the query such that the links will occur in the proper
	 * order.
	 * @internal
	 */
	static protected function getDependencyGraph(AbstractQuery $query, 
		                                         Dictionary $models) {
		
		// the table of relations established through the query
		$relations = $query->relations;
		$aliases = &$query->aliases;
		
		// to quickly access deeper areas in the graph, we will store
		// references to each place where these nodes show up in the graph
		// this is dependable based on the assumption that we've identified
		// all models uniquely through aliasing
		$entry_points = array();
		
		// we want to keep track of the trunks
		$trunks = array();
		
		// temporary indexes for through queries
		$t = 1;
		
		// populate the entry nodes array. this is actually *more* complicated
		// than solving datasource dependencies because we need to sneak
		// through relationships in.
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
				$path = AbstractRelation::findPath(
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
						$name = $i & 1 && $i < $count-1 ? 't'. $t++
						                                : $path[$i][0];
						
						// add the joining table into the link
						$relations[$last][] = $name;
						$entry_points[$name] = NULL;
						
						if(!isset($aliases[$name]))
							
							// this is listed in the aliases
							if(isset($aliases[$path[$i][0]]))
								$aliases[$name] = $aliases[$path[$i][0]];
							
							// it isn't listed in the aliases, it's likely
							// beensubstituted in by the relations path, take
							// it as is.
							else
								$aliases[$name] = $path[$i][0];
						
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
		//     'content' => array(),	  // content has no dependencies
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
	
	/**
	 * In the query we might be selecting from more than one model. That can
	 * be problematic, especially if there are items in the models that are
	 * also in other models. Thus we want to be able to find those conflicts
	 * and hopefully account for them gracefully. THis function does not
	 * attempt to solve conflicts for count queries.
	 */
	static protected function findConflictingItems(AbstractQuery $query, 
		                                           Dictionary $models) {
		
		$items = $query->items;
		
		// by default there are no conflicts
		$conflicts = array();
				
		// pre-processing:
		// there need to be at least two non-empty sets of items for there to
		// be conflicts. Go through the items and remove the ones that are
		// empty, also, fill in the ones that are selecting all items.
		foreach($items as $key => $set) {
			
			// no fields are being selected
			if(empty($set))
				unset($items[$key]);
			
			// all fields are being selected
			else if(isset($set[(string)ALL])) {
				$name = $query->aliases[$key];
				
				$items[$key] = array_flip(
					array_keys($models[$name]->_properties)
				);
			}
		}
				
		// processing:
		// find the conflicting fields through repeated array intersection.
		// this is unfortunately very inefficient for a large number of models
		// being selected from. Why does this need to be repeated? Well, we
		// can't just find conflicts for the first model against all the other
		// models, we need to find the conflicts between each model against
		// all the other models. A semi short-cut is taken using the array_
		// shift and merge that (todo) should be tested. We assume all prior
		// conflicts have been found.
		$items = array_values($items);
		while(count($items) > 1) {
			$conflicts = array_merge(
				$conflicts,
				call_user_func_array('array_intersect_key', $items)
			);
			array_shift($items);
		}
		
		// and array of conflict keys
		return $conflicts;
	}
	
	/**
	 * Compile the abstract query into something concrete. Apparently static
	 * functions can't be abstract.
	 */
	static public function compileSelect(AbstractQuery $query, Dictionary $models) {
		assert(FALSE);
	}
	static public function compileUpdate(AbstractQuery $query, Dictionary $models) {
		assert(FALSE);
	}
	static public function compileInsert(AbstractQuery $query, Dictionary $models) {
		assert(FALSE);
	}
	static public function compileDelete(AbstractQuery $query, Dictionary $models) {
		assert(FALSE);
	}
}
