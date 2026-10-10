<?php
/**
 * \Drupal\Sniffs\Semantics\TsPlaceholdersSniff
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */

namespace Drupal\Sniffs\Semantics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;
use PHPCSUtils\Utils\Arrays;
use PHPCSUtils\Utils\Numbers;
use PHPCSUtils\Utils\PassedParameters;
use PHPCSUtils\Utils\TextStrings;

/**
 * Checks that the placeholders of a CiviCRM ts() / E::ts() string match the
 * replacements passed in its second argument.
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */
class TsPlaceholdersSniff extends FunctionCall
{

    /**
     * Catch E::ts() in extensions, too.
     *
     * @var bool
     */
    protected $includeMethodCalls = true;

    /**
     * Keys of the ts() params array that are options, not replacements.
     *
     * @var array
     */
    protected $optionKeys = array(
                             'escape',
                             'skip',
                             'raw',
                             'plural',
                             'context',
                             'domain',
                             'skip_translation',
                            );

    /**
     * Options that turn off placeholder substitution in crm_translate().
     *
     * @var array
     */
    protected $noSubstitutionKeys = array(
                                     'skip',
                                     'raw',
                                    );

    /**
     * Array keys of SearchKit task messages, whose placeholders JavaScript fills.
     *
     * @var array
     */
    protected $deferredKeys = array(
                               'confirmMsg',
                               'runMsg',
                               'successMsg',
                               'errorMsg',
                              );


    /**
     * Returns an array of function names this test wants to listen for.
     *
     * @return array
     */
    public function registerFunctionNames()
    {
        return array('ts');

    }//end registerFunctionNames()


    /**
     * Processes this function call.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile    The file being scanned.
     * @param int                         $stackPtr     The position of the function call in
     *                                                  the stack.
     * @param int                         $openBracket  The position of the opening
     *                                                  parenthesis in the stack.
     * @param int                         $closeBracket The position of the closing
     *                                                  parenthesis in the stack.
     *
     * @return void
     */
    public function processFunctionCall(
        File $phpcsFile,
        $stackPtr,
        $openBracket,
        $closeBracket
    ) {
        $tokens  = $phpcsFile->getTokens();
        $args    = PassedParameters::getParameters($phpcsFile, $stackPtr);
        $textPtr = $this->literalString($phpcsFile, $args, 1);
        if ($textPtr === null) {
            return;
        }

        $message = TextStrings::stripQuotes($tokens[$textPtr]['content']);
        if (isset($args[2]) === false) {
            $this->checkWithoutParams($phpcsFile, $stackPtr, $textPtr, $message, $closeBracket);
            return;
        }

        $params = $this->parseArray($phpcsFile, $args[2]);
        if ($params === false) {
            // Params are not an array literal, nothing to compare.
            return;
        }

        foreach ($params['elements'] as $element) {
            if ($element['key'] === null) {
                $error = 'ts() replacement without a key is substituted for %0; use [1 => ...]';
                $phpcsFile->addError($error, $element['start'], 'PositionalReplacement');
            }
        }

        if ($params['dynamic'] === true) {
            // A computed key or spread could provide any placeholder.
            return;
        }

        $keys = array();
        foreach ($params['elements'] as $element) {
            if ($element['key'] !== null) {
                $keys[$element['key']] = $element;
            }
        }

        foreach ($this->noSubstitutionKeys as $option) {
            if (isset($keys[$option]) === true && $this->isFalsy($phpcsFile, $keys[$option]) === false) {
                return;
            }
        }

        $haystack = $message;
        if (isset($keys['plural']) === true) {
            $pluralPtr = $this->literalString($phpcsFile, array($keys['plural']), 0);
            if ($pluralPtr !== null) {
                $haystack .= "\n".TextStrings::stripQuotes($tokens[$pluralPtr]['content']);
            }
        }

        $used = array();
        preg_match_all('/%(\d+)/', $haystack, $matches);
        foreach (array_unique($matches[1]) as $digits) {
            $key = $this->matchNumericKey($digits, $keys);
            if ($key !== null) {
                $used[] = $key;
            } else if ($this->matchesStringKey($digits, $keys, $haystack) === false) {
                $error = 'ts() string contains %%%s but the replacements have no key %s';
                $phpcsFile->addError($error, $textPtr, 'MissingReplacement', array($digits, $digits));
            }
        }

        if (isset($keys['plural']) === true && $pluralPtr === null) {
            // A computed plural could use any replacement.
            return;
        }

        foreach ($keys as $key => $element) {
            if (in_array($key, $this->optionKeys, true) === true
                || ($key === 'count' && isset($keys['plural']) === true)
            ) {
                continue;
            }

            if (is_int($key) === true) {
                $isUsed = in_array($key, $used, true);
            } else {
                $isUsed = strpos($haystack, '%'.$key) !== false;
            }

            if ($isUsed === false) {
                $error = 'ts() replacement %%%s is not used in the translatable string';
                $phpcsFile->addError($error, $element['keyPtr'], 'UnusedReplacement', array($key));
            }
        }//end foreach

    }//end processFunctionCall()


    /**
     * Finds the key strtr() substitutes for "%<digits>": the longest prefix of
     * the digits that is a key.
     *
     * @param string $digits The digits after the percent sign.
     * @param array  $keys   The replacement keys.
     *
     * @return int|null
     */
    protected function matchNumericKey($digits, array $keys)
    {
        for ($length = strlen($digits); $length > 0; $length--) {
            $prefix = substr($digits, 0, $length);
            if (($prefix === '0' || $prefix[0] !== '0') && isset($keys[(int) $prefix]) === true) {
                return (int) $prefix;
            }
        }

        return null;

    }//end matchNumericKey()


    /**
     * Whether a string key starting with these digits, e.g. '01', occurs as
     * a placeholder.
     *
     * @param string $digits   The digits after the percent sign.
     * @param array  $keys     The replacement keys.
     * @param string $haystack The translatable strings.
     *
     * @return bool
     */
    protected function matchesStringKey($digits, array $keys, $haystack)
    {
        foreach (array_keys($keys) as $key) {
            if (is_string($key) === true
                && strpos($key, $digits) === 0
                && strpos($haystack, '%'.$key) !== false
            ) {
                return true;
            }
        }

        return false;

    }//end matchesStringKey()


    /**
     * Flags a ts() string with numbered placeholders but no replacements.
     *
     * Exempt: SearchKit task messages, whose placeholders JavaScript fills, and
     * strings assigned to a variable that str_replace() or strtr() fills later.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile    The file being scanned.
     * @param int                         $stackPtr     The ts token.
     * @param int                         $textPtr      The translatable string token.
     * @param string                      $message      The unquoted string.
     * @param int                         $closeBracket Closing parenthesis of ts().
     *
     * @return void
     */
    protected function checkWithoutParams(File $phpcsFile, $stackPtr, $textPtr, $message, $closeBracket)
    {
        // Placeholders start at %1; a lone %26 is more likely URL encoding.
        if (preg_match('/%1(?![0-9])/', $message) !== 1) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $callee = array(
                   T_STRING,
                   T_NS_SEPARATOR,
                   T_DOUBLE_COLON,
                   T_SELF,
                   T_STATIC,
                  );
        $before = $phpcsFile->findPrevious(array_merge(Tokens::$emptyTokens, $callee), ($stackPtr - 1), null, true);
        if ($tokens[$before]['code'] === T_DOUBLE_ARROW) {
            $key = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($before - 1), null, true);
            if ($tokens[$key]['code'] === T_CONSTANT_ENCAPSED_STRING
                && in_array(TextStrings::stripQuotes($tokens[$key]['content']), $this->deferredKeys, true) === true
            ) {
                return;
            }
        }

        if ($this->isSubstitutedLater($phpcsFile, $before) === true) {
            return;
        }

        if ($this->hasParamsOutside($phpcsFile, $stackPtr, $closeBracket) === true) {
            $error = 'ts() string contains %1 but its replacements are passed outside ts(); move the array into the ts() call';
            $phpcsFile->addError($error, $textPtr, 'ParamsOutsideTs');
            return;
        }

        $error = 'ts() string contains %1 but no replacements are passed; add [1 => ...] as the second argument of ts()';
        $phpcsFile->addError($error, $textPtr, 'MissingReplacement');

    }//end checkWithoutParams()


    /**
     * Detects $var = ts('...'); followed by str_replace() or strtr() on $var
     * later in the same scope.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $before    Token before the ts() callee.
     *
     * @return bool
     */
    protected function isSubstitutedLater(File $phpcsFile, $before)
    {
        $tokens = $phpcsFile->getTokens();
        if ($tokens[$before]['code'] !== T_EQUAL) {
            return false;
        }

        $variable = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($before - 1), null, true);
        if ($tokens[$variable]['code'] !== T_VARIABLE) {
            return false;
        }

        $scopeEnd = $phpcsFile->numTokens;
        if (empty($tokens[$variable]['conditions']) === false) {
            $scopes   = array_keys($tokens[$variable]['conditions']);
            $scopeEnd = $tokens[end($scopes)]['scope_closer'];
        }

        for ($i = ($before + 1); $i < $scopeEnd; $i++) {
            if ($tokens[$i]['code'] !== T_STRING
                || in_array(strtolower($tokens[$i]['content']), array('str_replace', 'strtr'), true) === false
            ) {
                continue;
            }

            foreach (PassedParameters::getParameters($phpcsFile, $i) as $arg) {
                $found = $phpcsFile->findNext(T_VARIABLE, $arg['start'], ($arg['end'] + 1), false, $tokens[$variable]['content']);
                if ($found !== false) {
                    return true;
                }
            }
        }

        return false;

    }//end isSubstitutedLater()


    /**
     * Detects foo(ts('... %1 ...'), [1 => $x]): the replacements are the next
     * argument of the enclosing call.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile    The file being scanned.
     * @param int                         $stackPtr     The ts token.
     * @param int                         $closeBracket Closing parenthesis of ts().
     *
     * @return bool
     */
    protected function hasParamsOutside(File $phpcsFile, $stackPtr, $closeBracket)
    {
        $tokens = $phpcsFile->getTokens();
        if (empty($tokens[$stackPtr]['nested_parenthesis']) === true) {
            return false;
        }

        $openers = array_keys($tokens[$stackPtr]['nested_parenthesis']);
        $callee  = $phpcsFile->findPrevious(Tokens::$emptyTokens, (end($openers) - 1), null, true);
        if ($tokens[$callee]['code'] !== T_STRING) {
            // Not a function call, e.g. array( or if (.
            return false;
        }

        $args = array_values(PassedParameters::getParameters($phpcsFile, $callee));
        foreach ($args as $index => $arg) {
            if ($arg['end'] < $closeBracket || isset($args[($index + 1)]) === false) {
                continue;
            }

            // The whole argument must be the ts() call, not an array or expression.
            if ($phpcsFile->findPrevious(Tokens::$emptyTokens, $arg['end'], null, true) !== $closeBracket) {
                return false;
            }

            $params = $this->parseArray($phpcsFile, $args[($index + 1)]);
            if ($params === false) {
                return false;
            }

            foreach ($params['elements'] as $element) {
                if (is_int($element['key']) === true && $this->matchNumericKey('1', array($element['key'] => true)) !== null) {
                    return true;
                }
            }

            return false;
        }//end foreach

        return false;

    }//end hasParamsOutside()


    /**
     * Returns the token of a parameter that is a single string literal.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $args      Parameters as PassedParameters returns them.
     * @param int                         $index     The parameter to check.
     *
     * @return int|null
     */
    protected function literalString(File $phpcsFile, array $args, $index)
    {
        if (isset($args[$index]) === false) {
            return null;
        }

        $start = $phpcsFile->findNext(Tokens::$emptyTokens, $args[$index]['start'], ($args[$index]['end'] + 1), true);
        $end   = $phpcsFile->findPrevious(Tokens::$emptyTokens, $args[$index]['end'], null, true);
        if ($start !== $end || $phpcsFile->getTokens()[$start]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        return $start;

    }//end literalString()


    /**
     * Splits an array literal argument into its elements.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $arg       The argument as PassedParameters returns it.
     *
     * @return array|false
     *   FALSE if the argument is not an array literal. Otherwise 'elements', each
     *   with 'key' (int, string or NULL when omitted), 'keyPtr' and the value's
     *   'start' and 'end', and 'dynamic' (TRUE if a key is computed or spread).
     */
    protected function parseArray(File $phpcsFile, array $arg)
    {
        $tokens = $phpcsFile->getTokens();
        $start  = $phpcsFile->findNext(Tokens::$emptyTokens, $arg['start'], ($arg['end'] + 1), true);
        $end    = $phpcsFile->findPrevious(Tokens::$emptyTokens, $arg['end'], null, true);
        if (in_array($tokens[$start]['code'], array(T_OPEN_SHORT_ARRAY, T_ARRAY), true) === false) {
            return false;
        }

        $openClose = Arrays::getOpenClose($phpcsFile, $start);
        if ($openClose === false || $openClose['closer'] !== $end) {
            // A list, or something like [...] + $more.
            return false;
        }

        $result = array(
                   'elements' => array(),
                   'dynamic'  => false,
                  );
        foreach (PassedParameters::getParameters($phpcsFile, $start) as $item) {
            $first = $phpcsFile->findNext(Tokens::$emptyTokens, $item['start'], ($item['end'] + 1), true);
            if ($tokens[$first]['code'] === T_ELLIPSIS) {
                $result['dynamic'] = true;
                continue;
            }

            $element = array(
                        'key'    => null,
                        'keyPtr' => $first,
                        'start'  => $first,
                        'end'    => $item['end'],
                       );
            $arrow   = Arrays::getDoubleArrowPtr($phpcsFile, $item['start'], $item['end']);
            if ($arrow !== false) {
                $element['key']   = $this->literalKey($phpcsFile, $first, $arrow);
                $element['start'] = $phpcsFile->findNext(Tokens::$emptyTokens, ($arrow + 1), ($item['end'] + 1), true);
                if ($element['key'] === null) {
                    $result['dynamic'] = true;
                    continue;
                }
            }

            $result['elements'][] = $element;
        }//end foreach

        return $result;

    }//end parseArray()


    /**
     * Whether an element's value is a literal PHP considers empty, e.g. FALSE or 0.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $element   The element from parseArray().
     *
     * @return bool
     */
    protected function isFalsy(File $phpcsFile, array $element)
    {
        $tokens = $phpcsFile->getTokens();
        $end    = $phpcsFile->findPrevious(Tokens::$emptyTokens, $element['end'], null, true);
        if ($element['start'] !== $end) {
            return false;
        }

        $token = $tokens[$end];
        switch ($token['code']) {
        case T_FALSE:
        case T_NULL:
            return true;
        case T_LNUMBER:
            return Numbers::getDecimalValue($token['content']) === '0';
        case T_CONSTANT_ENCAPSED_STRING:
            return in_array(TextStrings::stripQuotes($token['content']), array('', '0'), true);
        default:
            return false;
        }

    }//end isFalsy()


    /**
     * Returns the array key PHP would use, or NULL if it is not a plain literal.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $start     First token of the key.
     * @param int                         $arrow     The double arrow.
     *
     * @return int|string|null
     */
    protected function literalKey(File $phpcsFile, $start, $arrow)
    {
        if ($phpcsFile->findPrevious(Tokens::$emptyTokens, ($arrow - 1), null, true) !== $start) {
            return null;
        }

        $content = $phpcsFile->getTokens()[$start]['content'];
        switch ($phpcsFile->getTokens()[$start]['code']) {
        case T_LNUMBER:
            return (int) Numbers::getDecimalValue($content);
        case T_CONSTANT_ENCAPSED_STRING:
            if (strpos($content, '\\') !== false) {
                // Escape sequences are not decoded.
                return null;
            }

            $key = TextStrings::stripQuotes($content);
            if (preg_match('/^(0|[1-9][0-9]*)$/', $key) === 1) {
                return (int) $key;
            }
            return $key;
        default:
            return null;
        }

    }//end literalKey()


}//end class
