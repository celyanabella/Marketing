<?php
/*
* WooCommerce Reward Section Backend
*/
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if (! class_exists('WPNEO_WC_Reward')) {

    class WPNEO_WC_Reward{

        protected static $_instance;
        public static function instance(){
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        public function __construct(){
            add_filter('woocommerce_product_data_tabs',     array($this, 'wpneo_reward_tabs'));
            add_action('woocommerce_product_data_panels',   array($this, 'wpneo_reward_options_tab_content'));
            add_action('woocommerce_process_product_meta',  array($this, 'wpneo_reward_options_field_save'));

            //Show reward in woocommerce order details
            add_action('woocommerce_order_details_after_order_table', array($this, 'wpneo_selected_reward_in_order_view'));

            add_filter('the_content', array($this, 'wpneo_show_reward_in_general_tab'));
        }

        /*
        * Add Reward tab (Woocommerce).
        * Only show if type "Crowdfunding" Selected
        */
        function wpneo_reward_tabs($tabs){
            $tabs['reward'] = array(
                'label'     => __('Reward', 'wp-crowdfunding'),
                'target'    => 'reward_options',
                'class'     => array('show_if_neo_crowdfunding_options', 'show_if_neo_crowdfunding_options'),
            );
            return $tabs;
        }

        /*
        * Add Reward tab Content(Woocommerce).
        * Only show the fields under Reward Tab
        */
        function wpneo_reward_options_tab_content($post_id){
            global $post;

            $var = get_post_meta($post->ID, 'wpneo_reward', true);
            $var = stripslashes($var);
            $data_array = json_decode($var, true);

            $woocommerce_meta_field = array(
                // Pledge Amount
                array(
                    'id'            => 'wpneo_rewards_pladge_amount[]',
                    'label'         => __('Pledge Amount', 'wp-crowdfunding'),
                    'desc_tip'      => 'true',
                    'type'          => 'text',
                    'placeholder'   => __('Pledge Amount', 'wp-crowdfunding'),
                    'value'         => '',
                    'class'         => 'wc_input_price',
                    'field_type'    => 'textfield'
                ),
                // Reward Image
                array(
                    'id'            => 'wpneo_rewards_image_field[]',
                    'label'         => __('Image Field', 'wp-crowdfunding'),
                    'desc_tip'      => 'true',
                    'type'          => 'image',
                    'placeholder'   => __('Image Field', 'wp-crowdfunding'),
                    'value'         => '',
                    'class'         => '',
                    'field_type'    => 'image'
                ),
                // Reward Description
                array(
                    'id'            => 'wpneo_rewards_description[]',
                    'label'         => __('Reward', 'wp-crowdfunding'),
                    'desc_tip'      => 'true',
                    'type'          => 'text',
                    'placeholder'   => __('Reward Description', 'wp-crowdfunding'),
                    'value'         => '',
                    'field_type'    => 'textareafield',
                ),
                // Reward Month
                array(
                    'id'            => 'wpneo_rewards_endmonth[]',
                    'label'         => __('Estimated Delivery Month', 'wp-crowdfunding'),
                    'type'          => 'text',
                    'value'         => '',
                    'options'       => array(
                        ''    => __('- Select -', 'wp-crowdfunding'),
                        'jan' => __('January', 'wp-crowdfunding'),
                        'feb' => __('February', 'wp-crowdfunding'),
                        'mar' => __('March', 'wp-crowdfunding'),
                        'apr' => __('April', 'wp-crowdfunding'),
                        'may' => __('May', 'wp-crowdfunding'),
                        'jun' => __('June', 'wp-crowdfunding'),
                        'jul' => __('July', 'wp-crowdfunding'),
                        'aug' => __('August', 'wp-crowdfunding'),
                        'sep' => __('September', 'wp-crowdfunding'),
                        'oct' => __('October', 'wp-crowdfunding'),
                        'nov' => __('November', 'wp-crowdfunding'),
                        'dec' => __('December', 'wp-crowdfunding'),
                    ),
                    'field_type'    => 'selectfield',
                ),
                // Reward Year
                array(
                    'id'            => 'wpneo_rewards_endyear[]',
                    'label'         => __('Estimated Delivery Year', 'wp-crowdfunding'),
                    'type'          => 'text',
                    'value'         => '',
                    'options'       => array(
                        ''     => __('- Select -', 'wp-crowdfunding'),
                        '2016' => __('2016', 'wp-crowdfunding'),
                        '2017' => __('2017', 'wp-crowdfunding'),
                        '2018' => __('2018', 'wp-crowdfunding'),
                        '2019' => __('2019', 'wp-crowdfunding'),
                        '2020' => __('2020', 'wp-crowdfunding'),
                        '2021' => __('2021', 'wp-crowdfunding'),
                    ),
                    'field_type'    => 'selectfield',
                ),
                // Quantity (Number of Pledge Items)
                array(
                    'id'            => 'wpneo_rewards_item_limit[]',
                    'label'         => __('Quantity', 'wp-crowdfunding'),
                    'desc_tip'      => 'true',
                    'type'          => 'text',
                    'placeholder'   => __('Number of Rewards(Physical Product)', 'wp-crowdfunding'),
                    'value'         => '',
                    'class'         => 'wc_input_price',
                    'field_type'    => 'textfield'
                ),

            );
            ?>

            <div id='reward_options' class='panel woocommerce_options_panel'>
                <?php
                $display = 'block';
                $meta_count = count($data_array);
                $field_count = count($woocommerce_meta_field);
                if ( $meta_count > 0 ){ $display = 'none'; }

                /*
                * Print without value of Reward System for clone group
                */
                echo "<div class='reward_group' style='display:" . $display . ";'>";
                echo "<div class='campaign_rewards_field_copy'>";

                foreach ($woocommerce_meta_field as $value) {
                    switch ($value['field_type']) {

                        case 'textareafield':
                            woocommerce_wp_textarea_input($value);
                            break;

                        case 'selectfield':
                            woocommerce_wp_select($value);
                            break;

                        case 'image':
                            echo '<p class="form-field">';
                            echo '<label for="wpneo_rewards_image_field">'.$value["label"].'</label>';
                            echo '<input type="hidden" class="wpneo_rewards_image_field" name="'.$value["id"].'" value="" placeholder="'.$value["label"].'"/>';
                            echo '<span class="wpneo-image-container"></span>';
                            echo '<button class="wpneo-image-upload-btn shorter">'.__("Upload","wp-crowdfunding").'</button>';
                            echo '</p>';
                            break;

                        default:
                            woocommerce_wp_text_input($value);
                            break;
                    }
                }

                echo '<input name="remove_rewards" type="button" class="button tagadd removeCampaignRewards" value="' . __('- Remove', 'wp-crowdfunding') . '" />';
                echo "</div>";
                echo "</div>";


                /*
                * Print with value of Reward System
                */
                if ($meta_count > 0) {
                    if (is_array($data_array) && !empty($data_array)) {
                        foreach ($data_array as $k => $v) {
                            echo "<div class='reward_group'>";
                            echo "<div class='campaign_rewards_field_copy'>";
                            foreach ($woocommerce_meta_field as $value) {
                                if(isset( $v[str_replace('[]', '', $value['id'])] )){
                                    $value['value'] = $v[str_replace('[]', '', $value['id'])];
                                }else{
                                    $value['value'] = '';
                                }
                                switch ($value['field_type']) {

                                    case 'textareafield':
                                        $value['value'] = html_entity_decode($value['value'],ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                        woocommerce_wp_textarea_input($value);
                                        break;

                                    case 'selectfield':
                                        woocommerce_wp_select($value);
                                        break;

                                    case 'image':
                                        $image_id = $value['value'];
                                        $raw_id = $image_id;
                                        if( $image_id!=0 && $image_id!='' ){
                                            $image_id = wp_get_attachment_url( $image_id );
                                            $image_id = '<img width="100" src="'.$image_id.'"><span class="wpneo-image-remove">x</span>';
                                        }else{
                                            $image_id = '';
                                        }
                                        echo '<p class="form-field">';
                                        echo '<label for="wpneo_rewards_image_field">'.$value["label"].'</label>';
                                        echo '<input type="hidden" class="wpneo_rewards_image_field" name="'.$value["id"].'" value="'.$raw_id.'" placeholder="'.$value["label"].'"/>';
                                        echo '<span class="wpneo-image-container">'.$image_id.'</span>';
                                        echo '<button class="wpneo-image-upload-btn shorter">'.__("Upload","wp-crowdfunding").'</button>';
                                        echo '</p>';
                                        break;

                                    default:
                                        woocommerce_wp_text_input($value);
                                        break;
                                }
                            }
                            echo '<input name="remove_rewards" type="button" class="button tagadd removeCampaignRewards" value="' . __('- Remove', 'wp-crowdfunding') . '" />';
                            echo "</div>";
                            echo "</div>";
                        }
                    }
                }

                if (WPNEO_CROWDFUNDING_TYPE == 'free'){
                    ?>
                    <p class="description"><?php _e('pro version is required to add more than 1 reward', 'wp-crowdfunding') ?>. <a href="https://www.themeum.com/product/wp-crowdfunding-plugin/" target="_blank"> <?php _e('click here to get pro version', 'wp-crowdfunding') ?></a></p>
                    <?php
                } else {
                    ?>
                    <div id="rewards_addon_fields"></div>
                    <input name="save" type="button" class="button button-primary tagadd" id="addreward" value="<?php _e('+ Add Reward', 'wp-crowdfunding'); ?>">
                <?php } ?>
            </div>

            <?php
        }

        /*
        * Save Reward tab Data(Woocommerce).
        * Update Post Meta for Reward Tab
        */
        function wpneo_reward_options_field_save($post_id){
            if (!empty($_POST['wpneo_rewards_pladge_amount'])) {

                $wpneo_rewards_pladge_amount    = $_POST['wpneo_rewards_pladge_amount'];
                $wpneo_rewards_image_field      = $_POST['wpneo_rewards_image_field'];
                $wpneo_rewards_description      = $_POST['wpneo_rewards_description'];
                $wpneo_rewards_endmonth         = $_POST['wpneo_rewards_endmonth'];
                $wpneo_rewards_endyear          = $_POST['wpneo_rewards_endyear'];
                $wpneo_rewards_item_limit       = $_POST['wpneo_rewards_item_limit'];

                $total_update_field = count($wpneo_rewards_pladge_amount);

                $data = array();
                for ($i = 0; $i < $total_update_field; $i++) {
                    if (!empty($wpneo_rewards_pladge_amount[$i])) {
                        $data[] = array(
                            'wpneo_rewards_pladge_amount'   => intval($wpneo_rewards_pladge_amount[$i]),
                            'wpneo_rewards_image_field'     => intval($wpneo_rewards_image_field[$i]),
                            'wpneo_rewards_description'     => esc_textarea($wpneo_rewards_description[$i]),
                            'wpneo_rewards_endmonth'        => esc_html($wpneo_rewards_endmonth[$i]),
                            'wpneo_rewards_endyear'         => esc_html($wpneo_rewards_endyear[$i]),
                            'wpneo_rewards_item_limit'      => esc_html($wpneo_rewards_item_limit[$i]),
                        );
                    }
                }
                $data_json = json_encode( $data,JSON_UNESCAPED_UNICODE );
                update_post_meta($post_id, 'wpneo_reward', $data_json);
            }
        }

        /**
         * @param $order
         *
         * Show selected reward
         */
        public function wpneo_selected_reward_in_order_view($order){
            $order_id = $order->get_id();
            $html = '';

            $r = get_post_meta($order_id, 'wpneo_selected_reward', true);
            if ( ! empty($r) && is_array($r) ){
                $html .="<h2>".__('Selected Reward', 'wp-crowdfunding')."</h2>";
                if ( ! empty($r['wpneo_rewards_description'])){
                    $html .= "<div>{$r['wpneo_rewards_description']}</div>";
                }
                if ( ! empty($r['wpneo_rewards_pladge_amount'])){
                    $html .= "<div><abbr>".sprintf('Amount : %s, Delivery : %s', wc_price($r['wpneo_rewards_pladge_amount']), $r['wpneo_rewards_endmonth'].', '.$r['wpneo_rewards_endyear'] ) ."</abbr></div>";
                }
        
            }
            echo $html;
        }


        public function wpneo_show_reward_in_general_tab($content){
            if (is_product()) {

                global $post;
                $product = wc_get_product($post->ID);

                if ($product->get_type() === 'crowdfunding') {

                    $col_9 = '';
                    $col_3 = '';
                    $campaign_rewards = get_post_meta($post->ID, 'wpneo_reward', true);
                    $campaign_rewards = stripslashes($campaign_rewards);
                    $campaign_rewards_a = json_decode($campaign_rewards, true);
                    if (is_array($campaign_rewards_a)) {
                        if (count($campaign_rewards_a) > 0) {

                            $col_9 = 'tab_col_9';
                            $col_3 = 'tab_col_3';
                        }
                    }

                    $html = '';

                    $html .= "<div class='tab-description-wrap wpneo-clearfix'>";

                    $html .= "<div class='tab-description {$col_9} '>";
                    $html .= $content;
                    $html .= '</div>';

                    $html .= "<div class='tab-rewards {$col_3} '>";
                    ob_start();
                    wpneo_campaign_story_right_sidebar();
                    $html .= ob_get_clean();
                    $html .= '</div>';

                    $html .= '</div>';

                    return $html;
                }
            }
            return $content;


        }

    }
}
WPNEO_WC_Reward::instance();