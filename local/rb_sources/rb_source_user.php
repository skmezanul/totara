<?php

/**
 * A report builder source for the "user" table.
 */
class rb_source_user extends rb_base_source {

    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns;
    /**
     * Whether the "staff_facetoface_sessions" report exists or not (used to determine
     * whether or not to display icons that link to it)
     * @var boolean
     */
    private $staff_f2f;

    /**
     * Constructor
     * @global object $CFG
     */
    public function __construct() {
        global $CFG;
        $this->base = $CFG->prefix . 'user';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = array();
        $this->defaultcolumns = array();
        $this->defaultfilters = array();
        $this->requiredcolumns = array();
        $this->staff_f2f = get_field('report_builder', 'id', 'shortname', 'staff_facetoface_sessions');
        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    /**
     * Creates the array of rb_join objects required for this->joinlist
     *
     * @global object $CFG
     * @return array
     */
    private function define_joinlist() {
        global $CFG;

        $joinlist = array();
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'id');

        return $joinlist;
    }

    /**
     * Creates the array of rb_column_option objects required for
     * $this->columnoptions
     *
     * @return array
     */
    private function define_columnoptions() {
        $columnoptions = array();
        $this->add_user_fields_to_columns($columnoptions, 'base');
        $this->add_position_fields_to_columns($columnoptions);

        // A column to display a user's profile picture
        $columnoptions[] = new rb_column_option(
                        'user',
                        'userpicture',
                        'User\'s picture',
                        'base.id',
                        array(
                            'displayfunc' => 'user_picture',
                            'noexport' => true,
                            'defaultheading' => ' ',
                            'extrafields' => array(
                                'userpic_picture' => 'base.picture',
                                'userpic_firstname' => 'base.firstname',
                                'userpic_lastname' => 'base.lastname',
                                'userpic_imagealt' => 'base.imagealt'
                            )
                        )
        );

        // A column to display the "My Learning" icons for a user
        $columnoptions[] = new rb_column_option(
                        'user',
                        'userlearningicons',
                        'User\'s My Learning Icons',
                        'base.id',
                        array(
                            'displayfunc' => 'learning_icons',
                            'noexport' => true,
                            'defaultheading' => ' '
                        )
        );

        return $columnoptions;
    }

    /**
     * Creates the array of rb_filter_option objects required for $this->filteroptions
     * @return array
     */
    private function define_filteroptions() {
        // No filter options!
        return array();
    }

    /**
     * Creates the array of rb_content_option object required for $this->contentoptions
     * @return array
     */
    private function define_contentoptions() {
        $contentoptions = array();

        // Include the rb_user_content content options for this report
        $contentoptions[] = new rb_content_option('user', 'Users', 'base.id');
        return $contentoptions;
    }

    /**
     * A rb_column_options->displayfunc helper function to display the
     * "My Learning" icons for each user row
     *
     * @global object $CFG
     * @param integer $itemid ID of the user
     * @param object $row The rest of the data for the row
     * @return string
     */
    public function rb_display_learning_icons($itemid, $row) {
        global $CFG;

        $disp = '<span style="white-space:nowrap;">';

        // Learning Records icon
        $disp = '<a href="' . $CFG->wwwroot . '/my/records.php?id=' . $itemid . '"><img src="' . $CFG->wwwroot . '/pix/i/rol.png" title="' . get_string('learningrecords', 'local') . '" /></a>';

        // Face To Face Bookings icon
        if ($this->staff_f2f) {
            $disp .= '<a href="' . $CFG->wwwroot . '/my/bookings.php?id=' . $itemid . '"><img src="' . $CFG->wwwroot . '/pix/i/bookings.png" title="' . get_string('f2fbookings', 'local') . '" /></a>';
        }

        // Individual Development Plans icon
        $usercontext = get_context_instance(CONTEXT_USER, $itemid);
        if (has_capability('moodle/local:idpviewlist', $usercontext)) {
            $disp .= '<a href="' . $CFG->wwwroot . '/idp/index.php?userid=' . $itemid . '"><img src="' . $CFG->wwwroot . '/pix/i/idp.png" title="' . get_string('idp', 'idp') . '" /></a>';
        }

        $disp .= '</span>';
        return $disp;
    }

    /**
     * A rb_column_options->displayfunc helper function for showing a user's
     * profile picture
     * @param integer $itemid ID of the user
     * @param object $row The rest of the data for the row
     * @return string
     */
    public function rb_display_user_picture($itemid, $row) {
        $picuser = new stdClass();
        $picuser->id = $itemid;
        $picuser->picture = $row->userpic_picture;
        $picuser->imagealt = $row->userpic_imagealt;
        $picuser->firstname = $row->userpic_firstname;
        $picuser->lastname = $row->userpic_lastname;

        return print_user_picture($picuser, 1, null, null, true);
    }

}

// end of rb_source_user class
