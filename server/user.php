<?php
	// Copyright (c) 2014, Stephen Fewer of Harmony Security (www.harmonysecurity.com)
	// Licensed under a 3 clause BSD license (Please see LICENSE.txt)
	// Source code located at https://github.com/stephenfewer/grinder
	
	function user_isloggedin()
	{
		if( isset( $_SESSION['id'] ) and isset( $_SESSION['username'] ) and isset( $_SESSION['grinderkey'] ) )
		{
			if( $_SESSION['grinderkey'] == GRINDER_KEY )
				return true;
		}
		return false;
	}
	
	function user_isadministrator()
	{
		if( user_isloggedin() and isset( $_SESSION['type'] ) and $_SESSION['type'] == 0 )
			return true;
		return false;
	}
	
	function user_logout()
	{
		session_unset();

		session_destroy();
		
		$_SESSION = array();
		
		return true;
	}
	
	function user_valid_username( $username )
	{
		$username = trim( $username );
		if( empty( $username ) )
			return false;
		if( strlen( $username ) > 16 )
			return false;
		if( strlen( $username ) < 2 )
			return false;
		$result = preg_match( '/^[A-Za-z0-9_\-]+$/', $username ); 
		if( $result )
			return true;
		return false;
	}

	function user_valid_password( $password )
	{
		$password = trim( $password );
		if( empty( $password ) )
			return false;
		if( strlen( $password ) > 32 )
			return false;
		if( strlen( $password ) < 8 )
			return false;
		$result = preg_match( '/^[A-Za-z0-9_\-]+$/', $password ); 
		if( $result )
			return true;
		return false;
	}
	
	function user_valid_email( $email )
	{
		$result = preg_match( '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email );
		if( $result )
			return true;
		return false;
	}
	
	function user_change_password( $new_password )
	{
		$success = false;
		
		$new_password = mysql_real_escape_string( $new_password );

		if( user_valid_password( $new_password ) )
		{
			$password = sha1( GRINDER_SALT . $new_password );
			
			$sql = "UPDATE users SET password='" . $password . "' WHERE id='" . mysql_real_escape_string( $_SESSION['id'] ) . "' LIMIT 1;";
			$result = mysql_query( $sql );
			if( $result )
			{
				mysql_free_result( $result );
				$success = true;
			}
		}

		return $success;
	}
	
	function user_change_email( $new_email )
	{
		$success = false;
		
		if( user_valid_email( $new_email ) )
		{
			$sql = "UPDATE users SET email='" . mysql_real_escape_string( $new_email ). "' WHERE id='" . mysql_real_escape_string( $_SESSION['id'] ) . "' LIMIT 1;";
			$result = mysql_query( $sql );
			if( $result )
			{
				$_SESSION['email'] = $new_email;
				mysql_free_result( $result );
				$success = true;
			}
		}

		return $success;
	}
	
	function user_create( $name, $email, $password, $type )
	{
		$success = false;
		
		if( user_isadministrator() )
		{
			if( user_valid_password( $password ) and user_valid_username( $name ) )
			{
				$sql  = "INSERT INTO users ( name, email, password, type ) VALUES ";
				$sql .= "( '" . mysql_real_escape_string( $name ) . "', '" . mysql_real_escape_string( $email ) . "', '" . mysql_real_escape_string( sha1( GRINDER_SALT . $password ) ) . "', '" . mysql_real_escape_string( $type ) . "' );";
						
				$result = mysql_query( $sql );
				if( $result )
				{
					$success = true;
					mysql_free_result( $result );
				}
			}
		}
		
		return $success;
	}
	
	function user_delete( $id )
	{
		$success = false;
		if( user_isadministrator() )
		{
			if( $id != $_SESSION['id'] )
			{
				// delete from users
				$sql = "DELETE FROM users WHERE id='" . mysql_real_escape_string( $id ) . "';";
				$result = mysql_query( $sql );
				if( $result )
				{
					mysql_free_result( $result );
					
					// delete from logins
					$sql = "DELETE FROM logins WHERE id='" . mysql_real_escape_string( $id ) . "';";
					$result = mysql_query( $sql );
					if( $result )
					{
						mysql_free_result( $result );
						
						// delete from filters
						$sql = "DELETE FROM filters WHERE id='" . mysql_real_escape_string( $id ) . "';";
						$result = mysql_query( $sql );
						if( $result )
						{
							mysql_free_result( $result );
							
							// delete from alerts
							$sql = "DELETE FROM alerts WHERE id='" . mysql_real_escape_string( $id ) . "';";
							$result = mysql_query( $sql );
							if( $result )
							{
								$success = true;
								mysql_free_result( $result );
							}
						}
					}
				}
			}
		}
		return $success;
	}

	// Here we place any routines that must run to update an old version of the DB to the current expected schema.
	function update_db()
	{
		// If index doesn't exist, add an index to crashes for hash_quick to get a perf boost (credit: Jason Kratzer).
		$result1 = mysql_query( "SELECT * FROM INFORMATION_SCHEMA.STATISTICS WHERE table_name = 'crashes' AND COLUMN_NAME = 'hash_quick';" );
		if( $result1 )
		{
			if( mysql_num_rows( $result1 ) == 0 )
			{
				$result2 = mysql_query( "ALTER TABLE crashes ADD INDEX (hash_quick);" );
				if( $result2 )
					mysql_free_result( $result2 );
			}
			
			mysql_free_result( $result1 );
		}
		
		// Pull out the current DB schema version, or 0 if it doesn't exist...
		$schema_version = 0;
		
		$result1 = mysql_query( "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE table_name = 'grinder';" );
		if( $result1 )
		{
			if( mysql_num_rows( $result1 ) > 0 )
			{
				$result2 = mysql_query( "SELECT schema_version FROM grinder;" );
				if( $result2 )
				{
					if( mysql_num_rows( $result2 ) == 1 )
					{
						$row = mysql_fetch_array( $result2 );
						
						$schema_version = intval( $row['schema_version'] ) + 1;
					}
					
					mysql_free_result( $result2 );
				}
			}
			
			mysql_free_result( $result1 );
		}
		
		// Migrate the DB schema from its current version to the latest version...
		while( $schema_version <= 1 )
		{	
			switch( $schema_version )
			{
				case 0:
				{
					$migrate0 = mysql_query( "CREATE TABLE IF NOT EXISTS grinder ( schema_version int(11) NOT NULL DEFAULT 0 );" );
					if( $migrate0 )
					{
						mysql_free_result( $migrate0 );
						
						$migrate0 = mysql_query( "INSERT INTO grinder ( schema_version ) VALUES ( '0' );" );
						if( $migrate0 )
							mysql_free_result( $migrate0 );
					}

					break;
				}
				case 1:
				{
					// previously verified interesting == 1 and uninteresting ==2.
					// we want to migrate this so that verified interesting == 2 and uninteresting == 1.

					$t1_success = false;
					
					mysql_query( "START TRANSACTION;" );
					
					do
					{
						if( !mysql_query( "UPDATE crashes SET verified='12345' WHERE verified='2';" ) )
							break;
							
						if( !mysql_query( "UPDATE crashes SET verified='2' WHERE verified='1';" ) )
							break;
							
						if( !mysql_query( "UPDATE crashes SET verified='1' WHERE verified='12345';" ) )
							break;
							
						$t1_success = true;
						
					} while( 0 );
					
					if( $t1_success )
						mysql_query( "COMMIT;" );
					else
						mysql_query( "ROLLBACK;" );
						
					break;
				}
				/*case 2:
				{
					// Note: update the while loop above to be <= 2.
					$sql = "";
					$migrate2 = mysql_query( $sql );
					if( $migrate2 )
						mysql_free_result( $migrate2 );
						
					break;
				}*/
				default:
				{
					break;
				}
			}
			
			if( $schema_version > 0 )
			{
				$migrate = mysql_query( "UPDATE grinder SET schema_version='" . $schema_version . "';" );
				if( $migrate )
					mysql_free_result( $migrate );
			}	
			
			$schema_version += 1;
		}
	}
	
	function user_login( $username, $password )
	{
		$success = false;
		
		if( !user_valid_password( $password ) or !user_valid_username( $username ) )
			return false;
			
		$sql = "SELECT * FROM users WHERE name = '" . mysql_real_escape_string( $username ) . "' AND password = '" . mysql_real_escape_string( sha1( GRINDER_SALT . $password ) ) . "' LIMIT 1;";
		
		$result = mysql_query( $sql );
		if( $result )
		{
			if( mysql_num_rows( $result ) == 1 )
			{
				$row = mysql_fetch_array( $result );
				
				$_SESSION['id']             = $row['id'];
				$_SESSION['type']           = $row['type'];
				$_SESSION['email']          = $row['email'];
				$_SESSION['username']       = $username;
				$_SESSION['grinderkey']     = GRINDER_KEY;

				$sql = "INSERT INTO logins ( id, ip ) VALUES ( '" . $row['id'] . "', '" . mysql_real_escape_string( $_SERVER['REMOTE_ADDR'] ) . "' );";
				$result2 = mysql_query( $sql );
				if( $result2 )
					mysql_free_result( $result2 );
					
				$success = true;

				update_db();
			}
			
			mysql_free_result( $result );
		}
		
		return $success;
	}
?>