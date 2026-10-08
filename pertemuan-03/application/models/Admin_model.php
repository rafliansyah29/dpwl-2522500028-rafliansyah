<?php 
 
class Admin_model 
{ 
  private mysqli $db; 
 
  public function __construct() 
  { 
    global $db; 
    $this->db = $db; 
  } 
 
  public function findByUsername(string $username): ?array 
  { 
    $sql = " 
      SELECT username, password, status_akun 
      FROM t_admin 
      WHERE username = ? 
      LIMIT 1 
    "; 
 
    $stmt = $this->db->prepare($sql); 
    $stmt->bind_param('s', $username); 
    $stmt->execute(); 
 
    $result = $stmt->get_result(); 
    $row = $result->fetch_assoc(); 
    $stmt->close(); 
 
    return $row ?: null; 
  } 
}
