<?php

namespace Drupal\Sniffs\Semantics;

use Drupal\Test\CoderSniffUnitTest;

class FunctionTUnitTest extends CoderSniffUnitTest
{


    /**
     * Returns the lines where errors should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of errors that should occur on that line.
     *
     * @return array(int => int)
     */
    public function getErrorList($testFile = NULL)
    {
        return array(
                29 => 1,
                40 => 1,
               );

    }//end getErrorList()


    /**
     * Returns the lines where warnings should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of warnings that should occur on that line.
     *
     * @return array(int => int)
     */
    public function getWarningList($testFile = NULL)
    {
        return array(
                4 => 1,
                17 => 1,
                20 => 1,
                25 => 1,
                29 => 1,
                33 => 1,
                39 => 1,
                41 => 1,
                42 => 1,
                60 => 1,
                61 => 1,
                62 => 1,
                63 => 1,
                73 => 1,
                75 => 1,
                79 => 1,
                83 => 1,
                84 => 1,
                89 => 1,
                90 => 1,
                101 => 1,
                102 => 1,
               );

    }//end getWarningList()


}//end class
