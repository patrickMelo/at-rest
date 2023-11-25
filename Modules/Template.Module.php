<?php

namespace atREST\Modules;

use atREST\Core;
use atREST\Module;

class Template
{
	use Module;

	// Constants

	const Tag		    = 'Template';
	const DirectoryPath	= 'Templates/';
	const IncludeLimit  = 10;

	// Constructors & Destructors

	public function __construct()
	{
		if (self::$directoryPath == '') {
			self::$directoryPath = Core::DirectoryPath(Core::UserDirectory) . self::DirectoryPath;
		}
	}

	// Public Methods

	public function Load(string $templateName)
	{
		// Read the file contents.

		$templateFile = self::$directoryPath . $templateName . '.html';

		if (!Core::CheckPath($templateFile)) {
			$this->LogWarning('The template file "' . $templateFile . '" has an insecure path.');
			return false;
		}

		if (!is_file($templateFile) || !is_readable($templateFile)) {
			$this->LogError('The template file "' . $templateFile . '" does not exists or cannot be read.');
			return false;
		}

		if (($this->contents = file_get_contents($templateFile)) === false) {
			$this->contents = '';
			$this->LogError('Could not read the template file "' . $templateFile . '".');
			return false;
		}

		// Include all the needed parts.

		$this->IncludeParts();

		$this->loops = array();
		$this->loopIndex = -1;
	}

	public function Contents()
	{
		return $this->contents;
	}

	public function Start(string $loopName)
	{
		// If we are not in a loop yet we use the template contents to search
		// for the loop. Otherwise, we use the current loop contents for the search.

		if ($this->loopIndex < 0) {
			$currentContents = &$this->contents;
		} else {
			$currentContents = &$this->loops[$this->loopIndex]['currentContents'];
		}

		// Find the loop start tag.

		if (preg_match('/<!--[ ]*>>[ ]*' . $loopName . '[ ]*-->/', $currentContents, $loopMatches, PREG_OFFSET_CAPTURE) == 0) {
			return false;
		}

		$newLoop = array();

		$newLoop['start'] = intval($loopMatches[0][1]);
		$loopTemplateStart = intval($loopMatches[0][1]) + strlen($loopMatches[0][0]);

		// Find the loop end tag.

		if (preg_match('/<!--[ ]*<<[ ]*' . $loopName . '[ ]*-->/', $currentContents, $loopMatches, PREG_OFFSET_CAPTURE) == 0) {
			return false;
		}

		$newLoop['end'] = intval($loopMatches[0][1]) + strlen($loopMatches[0][0]);
		$loopTemplateLength = intval($loopMatches[0][1]) - $loopTemplateStart;

		$newLoop['template'] = substr($currentContents, $loopTemplateStart, $loopTemplateLength);
		$newLoop['currentContents'] = $newLoop['template'];
		$newLoop['contents'] = '';

		$this->loops[] = $newLoop;
		$this->loopIndex++;

		return true;
	}

	public function End()
	{
		if ($this->loopIndex < 0) {
			return false;
		}

		$currentLoop = &$this->loops[$this->loopIndex];

		// If we are in the first loop we insert the loop contents on the template.
		// Otherwise, we insert the loop contents on its parent loop.

		if ($this->loopIndex == 0) {
			$currentContents = &$this->contents;
		} else {
			$currentContents = &$this->loops[$this->loopIndex - 1]['currentContents'];
		}

		$currentContents = substr($currentContents, 0, $currentLoop['start']) . $currentLoop['contents'] . substr($currentContents, $currentLoop['end']);

		array_pop($this->loops);
		$this->loopIndex--;
		return true;
	}

	public function Loop()
	{
		if ($this->loopIndex < 0) {
			return false;
		}

		$this->loops[$this->loopIndex]['contents'] .= $this->loops[$this->loopIndex]['currentContents'];
		$this->loops[$this->loopIndex]['currentContents'] = $this->loops[$this->loopIndex]['template'];
	}

	public function Remove(string $loopName)
	{
		// If we are not in a loop yet we use the template contents to search
		// for the loop. Otherwise, we use the current loop contents for the search.

		if ($this->loopIndex < 0) {
			$currentContents = &$this->contents;
		} else {
			$currentContents = &$this->loops[$this->loopIndex]['currentContents'];
		}

		// Find the loop start tag.

		if (preg_match('/<!--[ ]*\>\>[ ]*' . $loopName . '[ ]*-->/', $currentContents, $loopMatches, PREG_OFFSET_CAPTURE) == 0) {
			return false;
		}

		$loopStart = intval($loopMatches[0][1]);

		// Find the loop end tag.

		if (preg_match('/<!--[ ]*\<\<[ ]*' . $loopName . '[ ]*-->/', $currentContents, $loopMatches, PREG_OFFSET_CAPTURE) == 0) {
			return false;
		}

		$loopEnd = intval($loopMatches[0][1]) + strlen($loopMatches[0][0]);
		$currentContents = substr($currentContents, 0, $loopStart) . substr($currentContents, $loopEnd);
		return true;
	}

	public function Set($varName, string $varValue = '')
	{
		// Where should we assing the variable(s)?

		if ($this->loopIndex < 0) {
			$currentContents = &$this->contents;
		} else {
			$currentContents = &$this->loops[$this->loopIndex]['currentContents'];
		}

		// Should we assing one or more variables?

		if (is_array($varName)) {
			foreach ($varName as $currentName => $currentValue) {
				$currentContents = str_replace('{' . $currentName . '}', $currentValue, $currentContents);
			}
		} else {
			$currentContents = str_replace('{' . $varName . '}', $varValue, $currentContents);
		}
	}

	public function SetBlock(string $blockName, array $blockData)
	{
		$this->Start($blockName);
		$this->Set($blockData);
		$this->Loop();
		$this->End();
	}

	// Private Methods

	private function IncludeParts()
	{
		$insertCount = 0;

		// TODO: use preg_match_all and loop through the resulting set using an offset variable to make this method faster.

		while (preg_match('/<!--[ ]*\+\+[ ]*([\w\/]+)[ ]*-->/', $this->contents, $insertMatches, PREG_OFFSET_CAPTURE) != 0) {
			$tagStart = intval($insertMatches[0][1]);
			$tagLength = strlen($insertMatches[0][0]);
			$partName = $insertMatches[1][0];

			// Load the part contents and insert it in the template contents.

			$partContents = '';
			$partFile = self::$directoryPath . $partName . '.html';

			if (!Core::CheckPath($partFile)) {
				$this->LogWarning('The template file "' . $partFile . '" has an insecure path.');
			} else if (!is_file($partFile) || !is_readable($partFile)) {
				$this->LogError('The template file "' . $partFile . '" does not exists or cannot be read.');
			} else if (($partContents = file_get_contents($partFile)) === false) {
				$this->LogError('Could not read the template file "' . $partFile . '".');
			}

			$this->contents = substr($this->contents, 0, $tagStart) . $partContents . substr($this->contents, $tagStart + $tagLength);

			// This prevents the script from entering an infinite loop if a part tries to include itself.

			$insertCount++;

			if ($insertCount >= self::IncludeLimit) {
				break;
			}
		}
	}

	// Private Members

	private $contents = '';
	private $loops = array(); // start, end, template, currentContents, contents
	private $loopIndex = -1;

	private static $directoryPath = '';
}
