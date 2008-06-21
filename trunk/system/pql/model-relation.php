<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A class that given two models will find an abstract path between them.
 * @author Peter Goodman
 */
class ModelRelation {
	
	const DIRECT = 1,
		  INDIRECT = 2;
	
	/**
	 * Given the alias of where we're starting to where we're trying to get,
	 * map out a path that can picked up by anything else between them.
	 */
	public static function findPath($from_alias, $to_alias, Dictionary $models) {
				
		$path = array();
		$current = $from_alias;
		
		// yes, given the while loop condition this is a bit redundant, but
		// the while loop is variable, of course.
		
		// check if we've already found the path that we're about to compute
		if(NULL !== ($start = $models[$current])) {
			if(isset($start->_cached_paths[$to_alias]))
				return $start->_cached_paths[$to_alias];
		}
		
		// keep a queue of the next models to look at
		$next_models = new Queue; 
		$next_models->push($to_alias);
				
		// walk the path from $from_alias to $to_alias and build up a
		// descriptive array of how to link every model in the path. Follow
		// direct relations and crack open indirect ones.
		while(NULL !== ($model = $models[$current]) && !$next_models->isEmpty()) {
						
			// shift the next model to look at off the queue
			$next = $next_models->shift();
			
			// relationship cannot be satisfied
			if(!isset($model->_relations[$next]) || !isset($models[$next])) {
				
				// cache the failed attempt
				return $start->_cached_paths[$to_alias] = array();
			}
			
			// the next model and how the current model is related to it
			$next_model = $models[$next];
			$how_to_relate = $model->_relations[$next];			
			
			// life is simple, we're dealing with a direct relationship.
			// Change $current to $next, and find out the new $next.
			if($how_to_relate[0] & self::DIRECT) {
												
				$next_key = $current_key = NULL;
				
				// now that we've established a direct relationship, we need
				// to figure out what maps one model to another. the mappings
				// could be stated in either model so we need to check both
				
				// the mapping is in this model
				if(isset($model->_mappings[$next]))
					list($next_key, $current_key) = $model->_mappings[$next];
				
				// the mapping is in the next model
				else if(isset($next_model->_mappings[$current]))
					list($current_key, $next_key) = $next_model->_mappings[$current];
								
				// a relationship could not be satisfied
				else {
					echo 'oh frak!';
					// cache the failed attempt
					return $start->_cached_paths[$to_alias] = array();
				}
				
				// add the mapping to the path
				$path[] = array($current, $current_key);
				$path[] = array($next, $next_key);
				
				// switch the $current item to look at (for the next iteration)
				// to the $next item of this loop. this process can be easily
				// visualized as walking up stairs.
				$current = $next;
				
			// life is slightly more complex, but not annoyingly so. Take off
			// what *should* be the $next model to look at and continue
			// without changing $current.
			} else {
				
				$old_nexts = $next_models->getArray();
				$next_models->clear();
				$next_models->extend($how_to_relate[1]);
				$next_models->push($next);
				$next_models->extend($old_nexts);
			}
		}
		
		// this is a direct path, so we can relate it both ways. this is
		// is useful when we are trying to resolve multiple direct
		// relationships later on
		if(count($path) == 2)
			$model->_cached_paths[$from_alias] = array_reverse($path);
		
		// cache it for possible later use
		$start->_cached_paths[$to_alias] = &$path;
		
		return $path;
	}
}
