<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A database exception will cause an internal error to occur which will yield
 * control to the errors controller.
 *
 * Note: often times the database exception will no be used. In those cases
 *       it is because the errors caused are usually programmer / configuration
 *       related and should be immediately fixed.
 *
 * @author Peter Goodman
 */
class DatabaseException extends ModelException {
	
}
