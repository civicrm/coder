<?php
/**
 * \Drupal\Sniffs\Semantics\FunctionTSniff
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */

namespace Drupal\Sniffs\Semantics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Check the usage of the t() and ts() functions to not escape translateable strings
 * with back slashes. Also checks that the first argument does not use string
 * concatenation.
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */
class FunctionTSniff extends FunctionCall
{

    /**
     * We also want to catch $this->t() calls in Drupal 8.
     *
     * @var bool
     */
    protected $includeMethodCalls = true;


    /**
     * Returns an array of function names this test wants to listen for.
     *
     * @return array
     */
    public function registerFunctionNames()
    {
        return array(
                't',
                'ts',
                'TranslatableMarkup',
                'TranslationWrapper',
               );

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
        $tokens   = $phpcsFile->getTokens();
        $name     = $tokens[$stackPtr]['content'];
        $data     = array('t');
        $argument = $this->getArgument(1);

        if ($name === 'ts') {
            if ($this->isForeignTsMethod($phpcsFile, $stackPtr) === true
                || $this->isInsideFunction($phpcsFile, $stackPtr, $name) === true
            ) {
                return;
            }

            $data = array('ts');
        }

        if ($argument === false) {
            $error = 'Empty calls to %s() are not allowed';
            $phpcsFile->addError($error, $stackPtr, 'EmptyT', $data);
            return;
        }

        if ($tokens[$argument['start']]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            // Not a translatable string literal.
            $warning = 'Only string literals should be passed to %s() where possible';
            $phpcsFile->addWarning($warning, $argument['start'], 'NotLiteralString', $data);
            return;
        }

        $string = $tokens[$argument['start']]['content'];
        if ($string === '""' || $string === "''") {
            $warning = 'Do not pass empty strings to %s()';
            $phpcsFile->addWarning($warning, $argument['start'], 'EmptyString', $data);
            return;
        }

        $concatAfter = $phpcsFile->findNext(Tokens::$emptyTokens, ($closeBracket + 1), null, true, null, true);
        if ($concatAfter !== false && $tokens[$concatAfter]['code'] === T_STRING_CONCAT) {
            $stringAfter = $phpcsFile->findNext(Tokens::$emptyTokens, ($concatAfter + 1), null, true, null, true);
            if ($stringAfter !== false
                && $tokens[$stringAfter]['code'] === T_CONSTANT_ENCAPSED_STRING
                && $this->checkConcatString($tokens[$stringAfter]['content']) === false
                && ($name !== 'ts' || $this->checkTsConcatString($tokens[$stringAfter]['content']) === false)
            ) {
                $warning = 'Do not concatenate strings to translatable strings, they should be part of the %s() argument and you should use placeholders';
                $phpcsFile->addWarning($warning, $stringAfter, 'ConcatString', $data);
            }
        }

        if ($name === 'ts') {
            $this->checkTsString($phpcsFile, $argument, $data);
        }

        $lastChar = substr($string, -1);
        if ($lastChar === '"' || $lastChar === "'") {
            $message = substr($string, 1, -1);
            if ($message !== trim($message)) {
                $warning = 'Translatable strings must not begin or end with white spaces, use placeholders with %s() for variables';
                $phpcsFile->addWarning($warning, $argument['start'], 'WhiteSpace', $data);
            }
        }

        $concatFound = $phpcsFile->findNext(T_STRING_CONCAT, $argument['start'], $argument['end']);
        if ($concatFound !== false) {
            $error = 'Concatenating translatable strings is not allowed, use placeholders instead and only one string literal';
            $phpcsFile->addError($error, $concatFound, 'Concat');
        }

        // Check if there is a backslash escaped single quote in the string and
        // if the string makes use of double quotes.
        if ($string[0] === "'" && strpos($string, "\'") !== false
            && strpos($string, '"') === false
        ) {
            $warn = 'Avoid backslash escaping in translatable strings when possible, use "" quotes instead';
            $phpcsFile->addWarning($warn, $argument['start'], 'BackslashSingleQuote');
            return;
        }

        if ($string[0] === '"' && strpos($string, '\"') !== false
            && strpos($string, "'") === false
        ) {
            $warn = "Avoid backslash escaping in translatable strings when possible, use '' quotes instead";
            $phpcsFile->addWarning($warn, $argument['start'], 'BackslashDoubleQuote');
        }

    }//end processFunctionCall()


    /**
     * Checks if a ts() method call belongs to something other than a civix helper.
     *
     * Only E::ts() and *ExtensionUtil::ts() translate; self::ts() or $obj->ts()
     * are unrelated methods.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the function name.
     *
     * @return bool
     */
    protected function isForeignTsMethod(File $phpcsFile, $stackPtr)
    {
        $tokens   = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        if ($tokens[$previous]['code'] === T_OBJECT_OPERATOR || $tokens[$previous]['code'] === T_NULLSAFE_OBJECT_OPERATOR) {
            return true;
        }

        if ($tokens[$previous]['code'] !== T_DOUBLE_COLON) {
            return false;
        }

        $class = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($previous - 1), null, true);
        $class = ltrim($tokens[$class]['content'], '\\');
        return $class !== 'E' && substr($class, -strlen('ExtensionUtil')) !== 'ExtensionUtil';

    }//end isForeignTsMethod()


    /**
     * Checks if the call sits inside a wrapper of the same name.
     *
     * Wrappers such as the civix-generated ts() or the Smarty block plugin
     * smarty_block_ts() pass their parameters through.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the function name.
     * @param string                      $name      The called function name.
     *
     * @return bool
     */
    protected function isInsideFunction(File $phpcsFile, $stackPtr, $name)
    {
        $function = $phpcsFile->getCondition($stackPtr, T_FUNCTION);
        if ($function === false) {
            return false;
        }

        $declared = $phpcsFile->getDeclarationName($function);
        return $declared === $name || $declared === 'smarty_block_'.$name;

    }//end isInsideFunction()


    /**
     * Checks a ts() string against the CiviCRM translation best practices.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $argument  The first argument of the call.
     * @param array                       $data      The function name for the messages.
     *
     * @return void
     */
    protected function checkTsString(File $phpcsFile, array $argument, array $data)
    {
        // PHPCS splits a string literal that spans lines into one token per line.
        $tokens  = $phpcsFile->getTokens();
        $literal = '';
        for ($i = $argument['start']; $i <= $argument['end'] && $tokens[$i]['code'] === T_CONSTANT_ENCAPSED_STRING; $i++) {
            $literal .= $tokens[$i]['content'];
        }

        $message = substr($literal, 1, -1);
        if (strpos($message, "\n") !== false || ($literal[0] === '"' && strpos($message, '\n') !== false)) {
            $warning = 'Translatable strings must not contain line breaks, keep the %s() string on a single line';
            $phpcsFile->addWarning($warning, $argument['start'], 'LineBreak', $data);
        }

        // Inline markup such as links or emphasis stays in the string, so sentences are not split.
        if (preg_match('/<\/?(p|div|br|hr|ul|ol|li|dl|dt|dd|table|thead|tbody|tfoot|tr|th|td|pre|blockquote|h[1-6])(\s[^>]*)?\/?>/i', $message) === 1) {
            $warning = 'Translatable strings must not contain block-level HTML such as paragraphs, line breaks, lists or tables, put the markup around %s() and translate each paragraph separately';
            $phpcsFile->addWarning($warning, $argument['start'], 'BlockTag', $data);
        }

        // Several placeholders without words still let translators reorder them.
        if (preg_match('/^(%\d+|\d+)?$/', preg_replace('/\s/', '', $message)) === 1) {
            $warning = 'Translatable strings must contain words, do not pass a lone placeholder or number to %s()';
            $phpcsFile->addWarning($warning, $argument['start'], 'NoWords', $data);
        }

    }//end checkTsString()


    /**
     * Checks if a string concatenated to ts() belongs to code around it.
     *
     * CiviCRM embeds ts() in markup, SQL, JavaScript and Smarty strings, and
     * uses it for file names.
     *
     * @param string $string The string that is concatenated to a ts() call.
     *
     * @return bool
     *   TRUE if the string is allowed to be concatenated with a translatable
     *   string, FALSE if not.
     */
    protected function checkTsConcatString($string)
    {
        $string = substr($string, 1, -1);

        // A file extension, or a quote that closes the string around the ts() call.
        if (preg_match('/^(\.[a-z0-9]+$|[)\]]*["\']|\{\/?[a-z])/i', $string) === 1) {
            return true;
        }

        $string = preg_replace('/\\\\n|&nbsp;|\s/', '', strip_tags($string));
        return $string === '' || $string === '|';

    }//end checkTsConcatString()


    /**
     * Checks if a string can be concatenated with a translatable string.
     *
     * @param string $string The string that is concatenated to a t() call.
     *
     * @return bool
     *   TRUE if the string is allowed to be concatenated with a translatable
     *   string, FALSE if not.
     */
    protected function checkConcatString($string)
    {
        // Remove outer quotes, spaces and HTML tags from the original string.
        $string = trim($string, '"\'');
        $string = trim(strip_tags($string));

        if ($string === '') {
            return true;
        }

        $allowed_items = array(
                          '(',
                          ')',
                          '[',
                          ']',
                          '-',
                          '<',
                          '>',
                          '«',
                          '»',
                          '\n',
                         );
        foreach ($allowed_items as $item) {
            if ($item === $string) {
                return true;
            }
        }

        return false;

    }//end checkConcatString()


}//end class
