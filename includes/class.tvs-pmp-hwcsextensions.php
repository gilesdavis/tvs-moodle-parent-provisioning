<?php
class TVS_PMP_HWCSExtensions extends TVS_PMP_Provisioner{

	public function get_moodle_studentids($auth, $idnumber) {
		// Get a list of UserIDs representing Students presented as an array
		// Expects:
		// $auth = The authentication type for Student users as defined in mdl_user
		// $idnumber = A string to match Student user types from based on contents of mdl_user.idnumber - Assumes using the LDAP auth plugin and an Active Directory LDAP path that allows Student users to be identified from other user types
		
		$stmt = $this->dbc->prepare( "SELECT id FROM {$this->dbprefix}user WHERE auth = ? AND deleted = ? AND idnumber LIKE ?" );
		if ( ! $stmt ) {
			throw new Exception( sprintf( __( 'Failed to prepare the database statement to get pupil user data. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
		}

		$zero = 0;
		$idnumber = "%$idnumber%";
		$stmt->bind_param( 'sis', $auth, $zero, $idnumber );
		$stmt->execute();
		$stmt->store_result();

		if ( $stmt->num_rows < 1 ) {
			// no results
			throw new Exception( sprintf( __( 'No users Returned.', 'tvs-moodle-parent-provisioning' ) ) );
			return 0;
		}


		$studentids_objs = array();

		$stmt->bind_result( $studentids );
		while ($stmt->fetch()) {
			$studentid = $studentids;
			$studentid_objs[] = $studentid;
		}
		$stmt->close();

		return ($studentid_objs);

	}

	public function add_student_roles($auth, $idnumber) {
		$this->logger->info( sprintf( __( 'Beginning to add Student roles ' . $studentid . '.', 'tvs-moodle-parent-provisioning' )) );
		// Adds Moodle roles to students in the context of themselves
		// Used to add Students as "Parents" of themselves to allow them to view their own PPV Data
		// Expects: $auth = The authentication type for Student users as defined in mdl_user
		// $idnumber = A string to match Student user types from based on contents of mdl_user.idnumber - Assumes using the LDAP auth plugin and an Active Directory LDAP path that allows Student users to be identified from other user types
		// $parent_context = The context ID for 'Parents'
		
		// Get a list of Students
		$student_array=$this->get_moodle_studentids($auth,$idnumber);
		foreach ($student_array as $studentid) {
			$context = TVS_PMP_Provisioner::CONTEXT_USER ;
			// Get the context for the Student
			$contextid = $this->get_context($context,$studentid,2);
			// If no context is returned for the user, log a warning and take no further action for this user
			if ($contextid < 1) {
				$this->logger->debug( sprintf( __( 'No context returned for User ' . $studentid . '.', 'tvs-moodle-parent-provisioning' )) );
				continue;
			}
			$roleid = $this->get_role_assignment ($studentid,$this->parent_role_id,$contextid);
			// If the role we are trying to add does not already exist, great - let's add it!
			if ($roleid < 1) {
				$this->logger->info( sprintf( __( 'Adding parent role returned for User ' . $studentid . '.', 'tvs-moodle-parent-provisioning' )) );
				$this->add_role_assignment ($studentid, $this->parent_role_id, $contextid, $this->modifier_id);  
			}
			// If the role we are trying to add already exists, we have nothing to do. Log and move on.
			else {
				$this->logger->debug( sprintf( __( 'Parent role already exists for User ' . $studentid . '.', 'tvs-moodle-parent-provisioning' )) );
			}

		}
	}
}
