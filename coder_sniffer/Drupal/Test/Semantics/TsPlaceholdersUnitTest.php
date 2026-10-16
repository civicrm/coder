<?php

namespace Drupal\Sniffs\Semantics;

use Drupal\Test\CoderSniffUnitTest;

class TsPlaceholdersUnitTest extends CoderSniffUnitTest
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
                28 => 1,
                29 => 1,
                32 => 1,
                33 => 1,
                34 => 2,
                37 => 1,
                38 => 1,
                39 => 2,
                40 => 1,
                41 => 1,
                44 => 2,
                45 => 1,
                57 => 1,
                58 => 1,
                59 => 1,
                60 => 1,
                62 => 1,
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
        return array();

    }//end getWarningList()


}//end class
