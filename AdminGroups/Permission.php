<?php

namespace ManiaLivePlugins\eXpansion\AdminGroups;

class Permission {

    const admingroups_onlyOwnGroup = 'admingroups_onlyOwnGroup';
    const admingroups_adminAllGroups = "admingroups_adminAllGroups";
    //Concerning Players
    const player_black = 'player_black';
    const player_unblack = 'player_unblack';
    const player_ban = 'player_ban';
    const player_unban = 'player_unban';
    const player_ignore = 'player_ignore';
    const player_forcespec = 'player_forcespec';
    const player_kick = 'player_kick';
    const player_guest = 'player_guest';
    const player_changeTeam = 'player_changeTeam';
    
    //concerning Server Settings
    const server_admin = 'server_admin';
    const server_stopDedicated = 'server_stopDedicated';
    const server_stopManialive = 'server_stopManialive';
    const server_name = 'server_name';
    const server_comment = 'server_comment';
    const server_password = 'server_password';
    const server_specpwd = 'server_specpwd';
    const server_refpwd = 'server_refpwd';
    const server_maxplayer = 'server_maxplayer';
    const server_maxspec = 'server_maxspec';
    const server_chattime = 'server_chattime';
    const server_refmode = 'server_refmode';
    const server_ladder = 'server_ladder';
    const server_votes = 'server_votes';
    const server_controlPanel = 'server_controlPanel';
    const server_database = 'server_database';
    const server_genericOptions = 'server_genericOptions';
    const server_usePlanets = 'server_planets';
    // conserning expansion
    const server_update = "server_update";
    const expansion_pluginSettings = "expansion_pluginSettings";
    const expansion_pluginStartStop = "expansion_pluginStartStop";
    //Concerning Game Settings      
    const game_gamemode = 'game_gamemode';
    const game_settings = 'game_settings';
    const game_matchSave = 'game_matchSave';
    const game_matchDelete = 'game_matchDelete';
    const game_matchSettings = 'game_matchSettings';
    // concerning maps
    const map_skip = 'map_skip';
    const map_restart = 'map_res';
    const map_endRound = 'map_endRound';
    const map_addLocal = 'map_addLocal';
    const map_addMX = 'map_addMX';
    const map_removeMap = 'map_removeMap';
    const map_jukebox_admin = "map_jukebox_admin";
    const map_jukebox_free = "map_jukebox_free";
//
    const team_balance = 'team_balance';
    
    const chat_adminChannel = "chat_adminchat";
    const chat_onDisabled = "chat_onDisabled";
    const quiz_admin = "quiz_admin";

	const localRecrods_delete = 'localRecords_delete';
    
}

?>
