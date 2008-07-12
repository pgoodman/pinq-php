<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * The base Exception class for the PINQ framework.
 */
class PinqException extends Exception { }



//class ParserException extends PinqException { }
//class PrinterException extends PinqException { }
//class HandlerException extends PinqException { }

//class CompositionException extends PinqException { }
class InvalidStateException extends PinqException { }
//class ModelException extends PinqException { }

class UnsatisfiedDependencyException extends PinqException { }

