<?php
/**
 * File holding the abstract class with the command generation logic
 *
 * @package Infinum\Commands
 */

declare(strict_types=1);

namespace Infinum\Commands;

use Symfony\Component\Console\Command\Command;

/**
 * Fetch abstract class
 *
 * @package Infinum\Commands
 */
abstract class FetchCommand extends Command
{

	/**
	 * GraphQL query for the desired results
	 *
	 * @param string $user User or organization identifier.
	 * @param int $count Number of repos to fetch.
	 * @param string|null $topic Specific topic to fetch. Optional.
	 * @param string|null $cursorId Specific cursor ID to fetch. Used for pagination. Optional.
	 *
	 * @return string GraphQL query string.
	 */
	abstract protected function getQuery(string $user, int $count = 10, ?string $topic = '', ?string $cursorId = ''): string;

	/**
	 * Trim the text to a specific width
	 *
	 * The solution was taken from StackOverflow
	 *
	 * @link https://stackoverflow.com/a/965269/629127
	 * @author karim79 <https://stackoverflow.com/users/70393/karim79>
	 *
	 * @param string $text Input text.
	 * @param int $wordNumber Number of words to trim to.
	 *
	 * @return string Trimmed text
	 */
	protected function trimWords(string $text, int $wordNumber = 10): string
	{
		if (str_word_count($text, 0) > $wordNumber) {
			$words = str_word_count($text, 2);
			$pos   = array_keys($words);
			$text  = substr($text, 0, $pos[$wordNumber]) . '...';
		}

		return $text;
	}

	/**
	 * Message color helper
	 *
	 * @param string $text Text to color.
	 * @param string $severity Severity modifier.
	 *
	 * @return string colored text
	 */
	protected function colorMessage(string $text, string $severity): string
	{
		switch ($severity) {
			case 'CRITICAL':
				$text = '<fg=#ff4444>' . $text . '</>';
				break;
			case 'HIGH':
				$text = '<fg=#ffbb33>' . $text . '</>';
				break;
			case 'MODERATE':
				$text = '<fg=#00C851>' . $text . '</>';
				break;
			default:
				$text = '<fg=#33b5e5>' . $text . '</>';
				break;
		}

		return $text;
	}
}
