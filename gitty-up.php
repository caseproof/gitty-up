<?php
/*
Plugin Name: GITty UP!
Plugin URI: http://blairwilliams.com
Description: This is a simple plugin that listens for GitHub's post hook -- when it recieves this post it automatically updates the latest commit hash in the database. The cron job contained in this plugin will then put the git repo to whatever revision was specified from the post hook.
Version: 0.0.0.1
Author: Caseproof, LLC
Author URI: http://blairwilliams.com
Text Domain: gitty-up
Copyright: 2004-2011, Caseproof, LLC

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if(!class_exists('GITtyUp'))
{
  class GITtyUp
  {
    function __construct()
    {
      add_action('admin_menu', array(&$this, 'menu'));
      add_action('init', array(&$this, 'github_posthook'), 0);
      add_action('admin_enqueue_scripts', array(&$this,'load_admin_scripts'));
    }
    
    public function menu()
    {
      add_menu_page(__('GITty UP!'), __('GITty UP!'), 'update_core', 'gtup_options', array(&$this,'options') );
    }
    
    public function load_admin_scripts()
    {
      wp_enqueue_script( 'jquery' );
    }

    public function options()
    {
      $commit_key = $this->get_commit_key();
      ?>
	      <div class="wrap">
          <h2><?php _e('GITty UP! Options'); ?></h2>
        
          <?php
          if( isset( $_POST['gtup_process'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'update-gittyup-settings' ) && current_user_can( 'update_core' ) )
          {
            update_option('gtup_repo_path', stripslashes($_POST['gtup_repo_path']));
            update_option('gtup_repo_commit', stripslashes($_POST['gtup_repo_commit']));
            update_option('gtup_update_type', stripslashes($_POST['gtup_update_type']));
           
            ?>
            <div class="updated"><p><?php _e('Settings Updated'); ?></p></div>  
            <?php
          }
        
          $gtup_update_type = get_option('gtup_update_type');
          $gtup_repo_path   = get_option('gtup_repo_path');
          $gtup_repo_commit = get_option('gtup_repo_commit');
        
          ?>
          <script type="text/javascript">
            jQuery(document).ready(function() {
              if( jQuery('#gtup_update_type').val() == 'cron' ) {
                jQuery('#gtup_cron').show();
              }
              jQuery('#gtup_update_type').change( function() {
                if( jQuery('#gtup_update_type').val() == 'cron' ) {
                  jQuery('#gtup_cron').show();
                }
                else {
                  jQuery('#gtup_cron').hide();
                }
              });
            });
          </script>
          <h3><?php _e('Github Post Hook URL'); ?></h3>
          <pre><?php echo site_url( '/gitty-up/posthook/'.$commit_key ); ?></pre>
          <form action="" method="post">
	          <?php wp_nonce_field( 'update-gittyup-settings' ) ?>

            <h3><?php _e('GIThub Update Type'); ?></h3>
            <select id="gtup_update_type" name="gtup_update_type">
              <option value="instant"<?php echo ($gtup_update_type=='instant')?' selected':''; ?>><?php _e('Instant'); ?></option>
              <option value="cron"<?php echo ($gtup_update_type=='cron')?' selected':''; ?>><?php _e('Cron (For use in Clustered Hosting)'); ?></option>
            </select>
            <div id="gtup_cron" style="display: none;">
              <h3><?php _e('Add this CRON to your system'); ?></h3>
              <pre>*/1 * * * * cd <?php echo WP_PLUGIN_DIR; ?>/gitty-up &amp;&amp; /usr/bin/php ./cron.php</pre>
            </div>
          
            <h3><?php _e('GIT Repo Configuration'); ?></h3>
            <input type="hidden" name="gtup_process" value="Y" />
            <table>
              <tr>
                <td><?php _e('GIT Repository Path') ?>:</td>
                <td><input type="text" name="gtup_repo_path" value="<?php echo esc_attr( $gtup_repo_path ); ?>" size="75" /></td>
              </tr>
              <tr>
                <td><?php _e('Latest Stable Commit') ?>:</td>
                <td><input type="text" name="gtup_repo_commit" value="<?php echo esc_attr( $gtup_repo_commit ); ?>" size="75" /></td>
              </tr>
            </table>
            <input type="submit" value="Update" />
          </form>
	      </div>
      <?php
    }

    public function github_posthook()
    {
      $regexp = "~^/(gitty-up)/(posthook)/(" . $this->get_commit_key() . ')$~';
      if(preg_match($regexp, $_SERVER['REQUEST_URI'], $matches))
      {
        if (!class_exists('Services_JSON'))
          require_once WP_PLUGIN_DIR . '/gitty-up/JSON.php';

        $json = new Services_JSON();
        $commit = $json->decode(stripslashes($_POST['payload']));

        if($commit and isset($commit->commits) and isset($commit->commits[0]) and isset($commit->commits[0]->id))
        {
          update_option('gtup_repo_commit', $commit->commits[0]->id);
          $gtup_update_type = get_option('gtup_update_type');

          if( $gtup_update_type == 'instant' )
            $this->git_update();

          _e("SUCCESS");
        }
        else
          _e("FAILURE");

        exit;
      }
    }

    public function get_commit_key()
    {
      $commit_key = get_option('gtup_commit_key');
      
      if(!$commit_key or empty($commit_key))
      {
        $commit_key = uniqid();
        update_option('gtup_commit_key', $commit_key);
      }

      return $commit_key;
    }
    
    public function git_update()
    {
      $gtup_repo_path   = get_option('gtup_repo_path');
      $gtup_repo_commit = get_option('gtup_repo_commit');

      $installed_commit = exec("cd $gtup_repo_path && git log --pretty=\"%H\" -1");

      if($installed_commit != $gtup_repo_commit)
      {
        // Fetch external commits
        exec("cd $gtup_repo_path && git fetch origin master");
        exec("cd $gtup_repo_path && git checkout $gtup_repo_commit");
      }
    }
  }
}

new GITtyUp;
