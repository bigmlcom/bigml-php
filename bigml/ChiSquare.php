<?php
include('ProbabilityDistribution.php');
include('Stats.php');
/**
 * ChiSquare class
 * 
 * Represents the Chi Square distribution, a distribution that represents the
 * sum of the squares of k independent standard normal random variates.
 *
 * For more information, see: http://en.wikipedia.org/wiki/Chi-squared_distribution
 */
class ChiSquare extends ProbabilityDistribution {
	private static $k;
	
	/**
	 * Constructor function
	 *
	 * @param float $k The number of degrees of freedom
	 */
	public function __construct($k = 1.0) {
		self::$k = $k;
	}
	
	/**
	 * Returns a random float
	 * 
	 * @return float The random variate.
	 */
	public function rvs() {
		return self::getRvs(self::$k);
	}
	
	/**
	 * Returns the probability distribution function
	 * 
	 * @param float $x The test value
	 * @return float The probability
	 */
	public function pdf($x) {
		return self::getPdf($x, self::$k);
	}
	
	/**
	 * Returns the cumulative distribution function, the probability of getting the test value or something below it
	 * 
	 * @param float $x The test value
	 * @return float The probability
	 */
	public function cdf($x) {
		return self::getCdf($x, self::$k);
	}
	
	/**
	 * Returns the survival function, the probability of getting the test value or something above it
	 * 
	 * @param float $x The test value
	 * @return float The probability
	 */
	public function sf($x) {
		return self::getSf($x, self::$k);
	}
	
	/**
	 * Returns the percent-point function, the inverse of the cdf
	 * 
	 * @param float $x The test value
	 * @return float The value that gives a cdf of $x
	 * @todo Unimplemented dependencies
	 */
	public function ppf($x) {
		return self::getPpf($x, self::$k);
	}
	
	/**
	 * Returns the inverse survival function, the inverse of the sf
	 * 
	 * @param float $x The test value
	 * @return float The value that gives an sf of $x
	 * @todo Unimplemented dependencies
	 */
	public function isf($x) {
		return self::getIsf($x, self::$k);
	}
	
	/**
	 * Returns the moments of the distribution
	 * 
	 * @param string $moments Which moments to compute. m for mean, v for variance, s for skew, k for kurtosis.  Default 'mv'
	 * @return type array A dictionary containing the first four moments of the distribution
	 */
	public function stats($moments = 'mv') {
		return self::getStats($moments, self::$k);
	}
	
	/**
	 * Returns a random float between $minimum and $minimum plus $maximum
	 * 
	 * @param float $k Shape parameter
	 * @return float The random variate.
	 * @static
	 */
	public static function getRvs($k = 1) {
		$k /= 2;
		$floork = floor($k);
		$fractionalk = $k - $floork;

		$sumLogUniform = 0;
		for ($index = 1; $index <= $floork; $index++) {
			$sumLogUniform += log(self::randFloat());
		}

		if ($fractionalk > 0) {
			$m = 0;
			$xi = 0;
			$V = array(0);
			do {
				$m++;

				$V[] = self::randFloat();
				$V[] = self::randFloat();
				$V[] = self::randFloat();

				if ($V[3*$m - 2] <= M_E/(M_E + $fractionalk)) {
					$xi = pow($V[3*$m - 1], 1/$fractionalk);
					$eta = $V[3*$m]*pow($xi, $fractionalk - 1);
				}
				else {
					$xi = 1 - log($V[3*$m - 1]);
					$eta = $V[3*$m]*exp(-$xi);
				}
			} while($eta > pow($xi, $fractionalk - 1)*exp(-$xi));
		}
		else {
			$xi = 0;
		}

		return 2*($xi - $sumLogUniform);
	}
	
	/**
	 * Returns the probability distribution function
	 * 
	 * @param float $x The test value
	 * @param float $k Shape parameter
	 * @return float The probability
	 * @static
	 */
	public static function getPdf($x, $k = 1) {
		return pow($x, $k/2.0 - 1)*exp(-$x/2.0)/(Stats::gamma($k/2.0)*pow(2, $k/2.0));
	}
	
	/**
	 * Returns the cumulative distribution function, the probability of getting the test value or something below it
	 * 
	 * @param float $x The test value
	 * @param float $k Shape parameter
	 * @return float The probability
	 * @static
	 */
	public static function getCdf($x, $k = 1) {
		return Stats::lowerGamma($k/2.0, $x/2)/Stats::gamma($k/2.0);
	}
	
	/**
	 * Returns the survival function, the probability of getting the test value or something above it
	 * 
	 * @param float $x The test value
	 * @param float $k Shape parameter
	 * @return float The probability
	 * @static
	 */
	public static function getSf($x, $k = 1) {
		return 1.0 - self::getCdf($x, $k);
	}
	
	/**
	 * Returns the percent-point function, the inverse of the cdf
	 * 
	 * @param float $x The test value
	 * @param float $k Shape parameter
	 * @return float The value that gives a cdf of $x
	 * @static
	 * @todo Unimplemented dependencies
	 */
	public static function getPpf($x, $k = 1) {
		return 2 * Stats::ilowerGamma($k / 2, $x * Stats::gamma($k / 2));
	}
	
	/**
	 * Returns the inverse survival function, the inverse of the sf
	 * 
	 * @param float $x The test value
	 * @param float $k Shape parameter
	 * @return float The value that gives an sf of $x
	 * @static
	 * @todo Unimplemented dependencies
	 */
	public static function getIsf($x, $k = 1) {
		return self::getPpf(1.0 - $x, $k);
	}
	
	/**
	 * Returns the moments of the distribution
	 * 
	 * @param string $moments Which moments to compute. m for mean, v for variance, s for skew, k for kurtosis.  Default 'mv'
	 * @param float $k Shape parameter
	 * @return type array A dictionary containing the first four moments of the distribution
	 * @static
	 */
	public static function getStats($moments = 'mv', $k = 1) {
		$return = array();
		
		if (strpos($moments, 'm') !== FALSE) $return['mean'] = $k;
		if (strpos($moments, 'v') !== FALSE) $return['variance'] = $k*2;
		if (strpos($moments, 's') !== FALSE) $return['skew'] = sqrt(8.0/$k);
		if (strpos($moments, 'k') !== FALSE) $return['kurtosis'] = 12.0/$k;
		
		return $return;
	}
}


function AChiSq($p,$n) { 
$v=0.5;
$dv=0.5; 
$x=0;
    while($dv>1e-15) {
        $x=1/$v-1;
        $dv=$dv/2;
        if (ChiSq($x,$n)>$p) {
            $v=$v-$dv;
        }
        else {
            $v=$v+$dv;
        } 
    }
    return $x;
}
function Norm($z) {
    $q=$z*$z;
   if (abs($z)>7)
        return (1-1/$q+3/($q*$q))*exp(-$q/2)/(abs($z)*sqrt(pi()/2));
    else
        return ChiSq($q,1);
}
function ChiSq($x,$n) {
    if ($x>1000 || $n>1000) {
            $q=Norm((pow($x/$n,1/3)+2/(9*$n)-1)/sqrt(2/(9*$n)))/2;
            if ($x>$n) 
                return $q;
            else
                return 1-$q; 
        }
    $p=exp(-0.5*$x);
        if(($n%2)==1) { $p=$p*sqrt(2*$x/pi());       }
    $k=$n; 
        while($k>=2) {
        $p=$p*$x/$k;
        $k=$k-2;
        }
   $t=$p;
     $a=$n;
     while($t>1e-15*$p) {
        $a=$a+2;
        $t=$t*$x/$a; 
        $p=$p+$t;
        }

    return 1-$p;
}
function calppm($conf,$fails,$total)
{
    $E5 = $conf/100;
  $F5 = $fails;
  $G5 = $total;

    $I5 = AChiSq((1-$E5),(2*($F5+1)))*1000000/(2*$G5);

    return round($I5);
}
