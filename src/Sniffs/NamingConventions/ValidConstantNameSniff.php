<?php declare(strict_types=1);

namespace Muvon\CodingStandard\Sniffs\NamingConventions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

use function preg_match;
use function sprintf;
use function strrpos;
use function strtolower;
use function substr;

use const T_CONST;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DOUBLE_COLON;
use const T_NULLSAFE_OBJECT_OPERATOR;
use const T_OBJECT_OPERATOR;
use const T_STRING;
use const T_WHITESPACE;

class ValidConstantNameSniff implements Sniff {
    public const CodeConstantNotMatchPattern = 'ConstantNotUpperCase';
    public const CodeClassConstantNotMatchPattern = 'ClassConstantNotUpperCase';
    private const DEFAULT_CONST_PTRN = '\b[A-Z][A-Z0-9_]*\b';

    public string $pattern = self::DEFAULT_CONST_PTRN;

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return int[]
     */
    public function register(): array {
			return [
				T_STRING,
				T_CONST,
			];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param File $PhpCsFile The file being scanned.
     * @param int  $stack_pos  The position of the current token in the stack passed in $tokens.
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public function process(File $PhpCsFile, $stack_pos): void {
			$tokens = $PhpCsFile->getTokens();

			if ($tokens[$stack_pos]['code'] === T_CONST) {
				// This is a class constant.
				$constant = $PhpCsFile->findNext(Tokens::$emptyTokens, $stack_pos + 1, null, true);
				if ($constant === false) {
					return;
				}

				$name = $tokens[$constant]['content'];

				if ($this->matchesRegex($name, $this->pattern)) {
					return;
				}

				$error = sprintf('Constant "%%s" does not match pattern "%s"', $this->pattern);
				$data = [$name];
				$PhpCsFile->addError(
					$error,
					$constant,
					self::CodeClassConstantNotMatchPattern,
					$data,
				);

				return;
			}

			// Only interested in define statements now.
			if (strtolower($tokens[$stack_pos]['content']) !== 'define') {
				return;
			}

			// Make sure this is not a method call.
			$prev = $PhpCsFile->findPrevious(T_WHITESPACE, $stack_pos - 1, null, true);
			if (
				$tokens[$prev]['code'] === T_OBJECT_OPERATOR
				|| $tokens[$prev]['code'] === T_DOUBLE_COLON
				|| $tokens[$prev]['code'] === T_NULLSAFE_OBJECT_OPERATOR
			) {
				return;
			}

			// If the next non-whitespace token after this token
			// is not an opening parenthesis then it is not a function call.
			$openBracket = $PhpCsFile->findNext(Tokens::$emptyTokens, $stack_pos + 1, null, true);
			if ($openBracket === false) {
				return;
			}

			// The next non-whitespace token must be the constant name.
			$constPtr = $PhpCsFile->findNext(T_WHITESPACE, $openBracket + 1, null, true);
			if ($tokens[$constPtr]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
				return;
			}

			$name = $tokens[$constPtr]['content'];

			// Strip namespace from constant like /foo/bar/CONSTANT.
			$split_pos = strrpos($name, '\\');
			if ($split_pos !== false) {
				$prefix = substr($name, 0, $split_pos + 1);
				$name = substr($name, $split_pos + 1);
			}

			if ($this->matchesRegex($name, $this->pattern)) {
				return;
			}

			$error = sprintf('Constant "%%s" does not match pattern "%s"', $this->pattern);
			$data = [$name];
			$PhpCsFile->addError($error, $stack_pos, self::CodeConstantNotMatchPattern, $data);
    }

		/**
		 * @param string $var
		 * @param string $pattern
		 * @return bool
		 */
    protected function matchesRegex(string $var, string $pattern): bool {
			return preg_match(sprintf('~%s~', $pattern), $var) === 1;
    }
}
