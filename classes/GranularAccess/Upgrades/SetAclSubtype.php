<?php

namespace GranularAccess\Upgrades;

use Elgg\Upgrade\AsynchronousUpgrade;
use Elgg\Upgrade\Result;

class SetAclSubtype implements AsynchronousUpgrade {

	/**
	 * {@inheritDoc}
	 * @see \Elgg\Upgrade\Batch::getVersion()
	 */
	public function getVersion() {
		return 2020041300;
	}

	/**
	 * {@inheritDoc}
	 * @see \Elgg\Upgrade\Batch::needsIncrementOffset()
	 */
	public function needsIncrementOffset() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 * @see \Elgg\Upgrade\Batch::shouldBeSkipped()
	 */
	public function shouldBeSkipped() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 * @see \Elgg\Upgrade\Batch::run()
	 * @throws \IOException
	 */
	public function run(Result $result, $offset) {
		$count = $this->countItems();

		$dbprefix = elgg_get_config('dbprefix');

		$sql = "UPDATE {$dbprefix}access_collections SET subtype = 'granular_access' WHERE name LIKE 'granular_access:%'";

		elgg()->db->updateData($sql);

		$result->addSuccesses($count);
		
		$result->markComplete();

		return $result;
	}

	/**
	 * {@inheritDoc}
	 * @see \Elgg\Upgrade\Batch::countItems()
	 */
	public function countItems() {
		$dbprefix = elgg_get_config('dbprefix');

		$sql = "SELECT COUNT(*) as count from {$dbprefix}access_collections WHERE name LIKE 'granular_access:%' AND subtype IS NULL";

		$result = elgg()->db->getData($sql);
		
		return (int) $result[0]->count;
	}
}
