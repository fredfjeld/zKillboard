<?php
/* zKillboard
 * Copyright (C) 2012-2013 EVE-KILL Team and EVSCO.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class cli_hourly implements cliCommand
{
	public function getDescription()
	{
		return "Tasks that needs to run every hour. |g|Usage: hourly";
	}

	public function getAvailMethods()
	{
		return "";
	}

	public function getCronInfo()
	{
		return array(
			3600 => ""
		);
	}

	public function execute($parameters)
	{
		self::apiPercentage();
		Db::execute("delete from zz_api_log where requestTime < date_sub(now(), interval 36 hour)");
		Db::execute("update zz_killmails set kill_json = '' where processed = 2 and killID < 0 and kill_json != ''");
		Db::execute("update zz_manual_mails set rawText = '' where killID > 0 and rawText != ''");

		$p = array();
		$p["limit"] = 5;
		$p["pastSeconds"] = 3 * 86400;
		$p["kills"] = true;

		Db::execute("analyze table zz_participants");
		Storage::store("Top3dayChars", json_encode(Info::doMakeCommon("Top Characters - Last 3 Days", "characterID", Stats::getTopPilots($p))));
		Storage::store("Top3dayCorps", json_encode(Info::doMakeCommon("Top Corporations - Last 3 Days", "corporationID", Stats::getTopCorps($p))));
		Storage::store("Top3dayAlli", json_encode(Info::doMakeCommon("Top Alliances - Last 3 Days", "allianceID", Stats::getTopAllis($p))));
		Primer::cachePrimer();
	}

	private static function apiPercentage()
	{
		$percentage = Storage::retrieve("LastHourPercentage", 10);
		$row = Db::queryRow("select sum(if(errorCode = 0, 1, 0)) good, sum(if(errorCode != 0, 1, 0)) bad from zz_api_characters");
		$good = $row["good"];
		$bad = $row["bad"];
		if ($bad > (($bad + $good) * ($percentage / 100))) {
			if($percentage > 15)
				Log::irc("|r|API gone haywire?  Over $percentage% of API's reporting an error atm.");
			$percentage += 5;
		} else if ($bad < (($bad + $good) * (($percentage - 5) / 100))) $percentage -= 5;
		if ($percentage < 10) $percentage = 10;
		Storage::store("LastHourPercentage", $percentage);
	}
}
