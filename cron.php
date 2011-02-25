<?php
/** This should be setup to run every hour or so. It will check the current repo hash
    against currently installed repo and change revisions when necessary.
*/
require_once('../../../wp-load.php');

$gtup_repo_path   = get_option('gtup_repo_path');
$gtup_repo_commit = get_option('gtup_repo_commit');

$installed_commit = exec("cd $gtup_repo_path && git log --pretty=\"%H\" -1");

echo "\n------------------------------------\n";
echo date();
echo "\n------------------------------------\n";
echo "GIT Repo Path: " . $gtup_repo_path . "\n";
echo "Installed Commit: " . $installed_commit . "\n";
echo "Current Commit: " . $gtup_repo_commit . "\n";

if($installed_commit != $gtup_repo_commit)
{
  // Fetch external commits
  exec("cd $gtup_repo_path && git fetch origin master");

  $install_out = exec("cd $gtup_repo_path && git checkout $gtup_repo_commit");
  echo "Installation Output: " . $install_out . "\n";
}

?>
