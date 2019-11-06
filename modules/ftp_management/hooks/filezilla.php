<?php

/**
 * @copyright 2019-2020 RactPanel Project (http://www.ractpanel.ractbd.com/) 
 * RactPanel is a GPL fork of the Sentora Project whose original header follows:
 *
 * ZPanel - A Cross-Platform Open-Source Web Hosting Control panel.
 * 
 * @package ZPanel
 * @version $Id$
 * @author Bobby Allen - ballen@bobbyallen.me
 * @copyright (c) 2008-2014 ZPanel Group - http://www.zpanelcp.com/
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License v3
 *
 * This program (ZPanel) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
foreach ($deletedclients as $deletedclient) {
    $sql = "SELECT * FROM x_ftpaccounts WHERE ft_acc_fk=:deleteClient AND ft_deleted_ts IS NULL";
    $info = $zdbh->prepare($sql);
    $info->bindParam(':deleteClient', $deletedclient);
    $info->execute();
    $result = $info->fetch();

    if ($result) {
        $sql = $zdbh->prepare("UPDATE x_ftpaccounts SET ft_deleted_ts=:time WHERE ft_acc_fk=:deleteClient");
        $sql->bindParam(':time', time());
        $sql->bindParam(':deleteClient', $deletedclient);
        $sql->execute();
    }
}

// Set some variables for filezilla from the x_ftp_settings database table...
$ftp_reload = "\"" . ctrl_options::GetSystemOption('ftp_service_root') . ctrl_options::GetSystemOption('ftp_service') . "\" /reload-config";
$ftpconfigfile = ctrl_options::GetSystemOption('ftp_config_file');
$getftpconfigfile = file_get_contents($ftpconfigfile);
$ftpsettings = '/<Settings>(.*)<\/Settings>/msU';

// Included after acount has been created, deleted, or reset...
$matchresult = preg_match_all($ftpsettings, $getftpconfigfile, $matches);
if (!empty($matchresult)) {
    // First grab the filezilla setting so we can add it back in when we write the config file...
    $line = "<FileZillaServer>" . fs_filehandler::NewLine();
    $line .= $matches[0][0] . fs_filehandler::NewLine();
    $line .= "<Groups />" . fs_filehandler::NewLine();
    $line .= "<Users>" . fs_filehandler::NewLine();
    // Get a list of all the accounts...
    $sql = $zdbh->prepare("SELECT * FROM x_ftpaccounts WHERE ft_deleted_ts IS NULL ORDER BY ft_user_vc ASC");
    $sql->execute();
    while ($rowftpaccounts = $sql->fetch()) {
        $ftpuser = ctrl_users::GetUserDetail($rowftpaccounts['ft_acc_fk']);
        //Only add the user if they are not disabled...
        if ($ftpuser['enabled'] <> 0) {
            // begin user loop
            $line .= "<User Name=\"" . $rowftpaccounts['ft_user_vc'] . "\">" . fs_filehandler::NewLine();
            $line .= "<Option Name=\"Pass\">" . md5($rowftpaccounts['ft_password_vc']) . "</Option>" . fs_filehandler::NewLine();
            $line .= "<Option Name=\"Group\"/>" . fs_filehandler::NewLine();
            $line .= "<Option Name=\"Bypass server userlimit\">0</Option>" . fs_filehandler::NewLine();
            $line .= "<Option Name=\"User Limit\">0</Option>" . fs_filehandler::NewLine();
            $line .= "<Option Name=\"IP Limit\">0</Option>" . fs_filehandler::NewLine();
            $line .= "<Option Name=\"Enabled\">1</Option>" . fs_filehandler::NewLine();
            $line .= "<Option Name=\"Comments\">Auto account generated by RactPanel (v. " . ctrl_options::GetSystemOption('dbversion') . ")</Option>" . fs_filehandler::NewLine();
            $line .= "<Option Name=\"ForceSsl\">0</Option>" . fs_filehandler::NewLine();
            $line .= "<IpFilter>" . fs_filehandler::NewLine();
            $line .= "<Disallowed/>" . fs_filehandler::NewLine();
            $line .= "<Allowed/>" . fs_filehandler::NewLine();
            $line .= "</IpFilter>" . fs_filehandler::NewLine();
            $line .= "<Permissions>" . fs_filehandler::NewLine();
            $line .= "<Permission Dir=\"" . ctrl_options::GetSystemOption('hosted_dir') . $ftpuser['username'] . $rowftpaccounts['ft_directory_vc'] . "\">" . fs_filehandler::NewLine();
            // If read only...
            $accessmode = "Read List";
            if ($rowftpaccounts['ft_access_vc'] == 'RO') {
                $permissionset = "<Option Name=\"FileRead\">1</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"FileWrite\">0</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"FileDelete\">0</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"FileAppend\">0</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirCreate\">0</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirDelete\">0</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirList\">1</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirSubdirs\">1</Option>" . fs_filehandler::NewLine();
                $accessmode = "Read access";
            }
            // If write only...
            if ($rowftpaccounts['ft_access_vc'] == 'WO') {
                $permissionset = "<Option Name=\"FileRead\">0</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"FileWrite\">1</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"FileDelete\">0</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"FileAppend\">0</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirCreate\">1</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirDelete\">0</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirList\">0</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirSubdirs\"0</Option>" . fs_filehandler::NewLine();
                $accessmode = "Write access";
            }
            // If read and write...
            if ($rowftpaccounts['ft_access_vc'] == 'RW') {
                $permissionset = "<Option Name=\"FileRead\">1</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"FileWrite\">1</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"FileDelete\">1</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"FileAppend\">1</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirCreate\">1</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirDelete\">1</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirList\">1</Option>" . fs_filehandler::NewLine();
                $permissionset .= "<Option Name=\"DirSubdirs\">1</Option>" . fs_filehandler::NewLine();
                $accessmode = "Full access";
            }

            $line .= $permissionset;
            $line .= "<Option Name=\"IsHome\">1</Option>" . fs_filehandler::NewLine();
            $line .= "<Option Name=\"AutoCreate\">0</Option>" . fs_filehandler::NewLine();
            $line .= "</Permission>" . fs_filehandler::NewLine();
            $line .= "</Permissions>" . fs_filehandler::NewLine();
            $line .= "<SpeedLimits DlType=\"0\" DlLimit=\"10\" ServerDlLimitBypass=\"0\" UlType=\"0\" UlLimit=\"10\" ServerUlLimitBypass=\"0\">" . fs_filehandler::NewLine();
            $line .= "<Download/>" . fs_filehandler::NewLine();
            $line .= "<Upload/>" . fs_filehandler::NewLine();
            $line .= "</SpeedLimits>" . fs_filehandler::NewLine();
            $line .= "</User>" . fs_filehandler::NewLine();
            // end user loop
        }
    }

    $line .= "</Users>" . fs_filehandler::NewLine();
    $line .= "</FileZillaServer>";

    // Write the Filezilla config file
    if (fs_filehandler::UpdateFile($ftpconfigfile, 0777, $line)) {
        exec($ftp_reload);
    }
}
?>