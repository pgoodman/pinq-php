<?php

/**
 * year([int $time]) -> string
 * 
 * Given a UNIX timestamp, return the year.
 */
function year($time = 0) {
	if(!$time) $time = time();
	return date('Y', $time);
}

/**
 * month([int $time[, bool $leading_zero]]) -> string
 *
 * Given a UNIX timestamp, return the month.
 */
function month($time = 0, $leading_zero = FALSE) {
	if(!$time) $time = time();
	return (string)date($leading_zero ? 'm' : 'n', $time);
}

/**
 * day([int $time[, bool $leading_zero]]) -> string
 *
 * Given a UNIX timestamp, return the day.
 */
function day($time = 0, $leading_zero = FALSE) {
	if(!$time) $time = time();
	return (string)date($leading_zero ? 'd' : 'j', $time);
}