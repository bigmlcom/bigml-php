<?php
/**
 * ProbabilityDistribution class
 * 
 * Parent class to all probability distributions.  Enforces a common interface
 * across subclasses and provides internal utility functions to them.
 */

namespace BigML;

abstract class ProbabilityDistribution {
	//Internal Utility Functions
	protected static function randFloat() {
		return ((float)mt_rand())/mt_getrandmax(); //A number between 0 and 1.
	}

	protected static function BernoulliTrial($p = 0.5) {
		$standardVariate = ((float)mt_rand())/mt_getrandmax();
		return ($standardVariate <= $p)?1:0;
	}
}
