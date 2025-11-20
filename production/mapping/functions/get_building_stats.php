<?php
require_once __DIR__ . '/db_utils.php';

function getBuildingStats($pdo, $buildingId) {
	try {
		if (!validateBuildingId($buildingId)) {
			return [
				'success' => false,
				'message' => 'Invalid building ID'
			];
		}

		// Fetch building details from buildings table only
		$buildingStmt = $pdo->prepare("SELECT 
			id,
			user_id,
			device_id,
			barangay_id,
			geo_fence_id,
			building_name,
			building_type,
			address,
			contact_person,
			contact_number,
			total_floors,
			has_sprinkler_system,
			has_fire_alarm,
			has_fire_extinguishers,
			has_emergency_exits,
			has_emergency_lighting,
			has_fire_escape,
			last_inspected,
			latitude,
			longitude,
			construction_year,
			building_area,
			created_at
		FROM buildings WHERE id = :id LIMIT 1");
		$buildingStmt->execute([':id' => $buildingId]);
		$building = $buildingStmt->fetch(PDO::FETCH_ASSOC);

		if (!$building) {
			return [
				'success' => false,
				'message' => 'Building not found'
			];
		}

		return [
			'success' => true,
			'building' => $building
		];
	} catch (PDOException $e) {
		return handleDatabaseError($e);
	}
}
?>


