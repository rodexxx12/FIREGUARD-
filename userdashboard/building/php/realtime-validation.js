// Real-time validation for Building Registration Form
$(function () {
    // Helper functions
    function setValid(input) {
        input.removeClass('is-invalid').addClass('is-valid');
        input.siblings('.invalid-feedback').hide();
    }
    function setInvalid(input, message) {
        input.removeClass('is-valid').addClass('is-invalid');
        input.siblings('.invalid-feedback').text(message).show();
    }
    function clearValidation(input) {
        input.removeClass('is-valid is-invalid');
        input.siblings('.invalid-feedback').hide();
    }

    // Building Name
    $('#building_name').on('input blur', function () {
        const input = $(this);
        if (!input.val().trim()) {
            setInvalid(input, 'Please provide a building name');
        } else {
            setValid(input);
        }
    });

    // Building Type
    $('#building_type').on('change blur', function () {
        const input = $(this);
        if (!input.val()) {
            setInvalid(input, 'Please select a building type');
        } else {
            setValid(input);
        }
    });

    // Total Floors with type and area-based validation
    $('#total_floors').on('input blur', function () {
        const input = $(this);
        const val = parseInt(input.val(), 10);
        const buildingType = $('#building_type').val();
        const buildingArea = parseFloat($('#building_area').val()) || 0;
        
        if (!val || val < 1) {
            setInvalid(input, 'Please enter a valid number of floors (minimum 1)');
        } else {
            // Type and area-based floor validation
            let maxFloors = 200; // Default maximum
            let floorMessage = '';

            switch (buildingType) {
                case 'residential':
                    maxFloors = 50; // Residential buildings typically max 50 floors
                    floorMessage = 'Residential buildings typically have 1-50 floors';
                    break;
                case 'commercial':
                    maxFloors = 100; // Commercial buildings can be taller
                    floorMessage = 'Commercial buildings typically have 1-100 floors';
                    break;
                case 'industrial':
                    maxFloors = 10; // Industrial buildings are usually low-rise
                    floorMessage = 'Industrial buildings typically have 1-10 floors';
                    break;
                case 'educational':
                    maxFloors = 15; // Educational buildings are usually mid-rise
                    floorMessage = 'Educational buildings typically have 1-15 floors';
                    break;
                case 'healthcare':
                    maxFloors = 20; // Healthcare buildings are usually mid-rise
                    floorMessage = 'Healthcare buildings typically have 1-20 floors';
                    break;
            }

            // Area-based floor validation
            if (buildingArea > 0) {
                const areaPerFloor = buildingArea / val;
                
                if (areaPerFloor < 50) {
                    setInvalid(input, `Floor area too small (${areaPerFloor.toFixed(1)} sqm per floor). Consider reducing floors or increasing total area.`);
                } else if (areaPerFloor > 2000) {
                    setInvalid(input, `Floor area too large (${areaPerFloor.toFixed(1)} sqm per floor). Consider adding floors or reducing total area.`);
                } else if (val > maxFloors) {
                    setInvalid(input, `Too many floors for ${buildingType} buildings. ${floorMessage}`);
                } else {
                    setValid(input);
                }
            } else if (val > maxFloors) {
                setInvalid(input, `Too many floors for ${buildingType} buildings. ${floorMessage}`);
            } else {
                setValid(input);
            }
        }
    });

    // Construction Year
    $('#construction_year').on('input blur', function () {
        const input = $(this);
        const year = parseInt(input.val(), 10);
        const currentYear = new Date().getFullYear();
        if (input.val() && (year < 1800 || year > currentYear)) {
            setInvalid(input, `Please enter a year between 1800 and ${currentYear}`);
        } else {
            clearValidation(input);
            if (input.val()) setValid(input);
        }
    });

    // Building Area with type-based validation
    $('#building_area').on('input blur', function () {
        const input = $(this);
        const val = parseFloat(input.val());
        const buildingType = $('#building_type').val();
        
        if (input.val()) {
            if (isNaN(val) || val < 0) {
                setInvalid(input, 'Please enter a valid area');
            } else {
                // Type-specific area validation
                let minArea = 0;
                let maxArea = 100000; // 100,000 sqm default max
                let areaMessage = '';

                switch (buildingType) {
                    case 'residential':
                        minArea = 20; // Minimum 20 sqm for residential
                        maxArea = 5000; // Maximum 5,000 sqm for residential
                        areaMessage = 'Residential buildings typically range from 20 to 5,000 sqm';
                        break;
                    case 'commercial':
                        minArea = 50; // Minimum 50 sqm for commercial
                        maxArea = 50000; // Maximum 50,000 sqm for commercial
                        areaMessage = 'Commercial buildings typically range from 50 to 50,000 sqm';
                        break;
                    case 'industrial':
                        minArea = 100; // Minimum 100 sqm for industrial
                        maxArea = 100000; // Maximum 100,000 sqm for industrial
                        areaMessage = 'Industrial buildings typically range from 100 to 100,000 sqm';
                        break;
                    case 'educational':
                        minArea = 200; // Minimum 200 sqm for educational
                        maxArea = 20000; // Maximum 20,000 sqm for educational
                        areaMessage = 'Educational buildings typically range from 200 to 20,000 sqm';
                        break;
                    case 'healthcare':
                        minArea = 300; // Minimum 300 sqm for healthcare
                        maxArea = 30000; // Maximum 30,000 sqm for healthcare
                        areaMessage = 'Healthcare buildings typically range from 300 to 30,000 sqm';
                        break;
                }

                if (val < minArea) {
                    setInvalid(input, `Area too small for ${buildingType} buildings. ${areaMessage}`);
                } else if (val > maxArea) {
                    setInvalid(input, `Area too large for ${buildingType} buildings. ${areaMessage}`);
                } else {
                    setValid(input);
                }
            }
        } else {
            clearValidation(input);
        }
    });

    // Contact Person
    $('#contact_person').on('input blur', function () {
        const input = $(this);
        const val = input.val().trim();
        if (val && val.length < 2) {
            setInvalid(input, 'Contact person name must be at least 2 characters');
        } else if (val && !/^[a-zA-Z\s\.\-']+$/.test(val)) {
            setInvalid(input, 'Contact person name can only contain letters, spaces, dots, hyphens, and apostrophes');
        } else {
            clearValidation(input);
            if (val) setValid(input);
        }
    });

    // Contact Number with Philippine mobile number validation
    $('#contact_number').on('input blur', function () {
        const input = $(this);
        const val = input.val().trim();
        
        if (val) {
            // Remove all non-digit characters for validation
            const digitsOnly = val.replace(/[^0-9]/g, '');
            
            // Check for invalid characters
            if (!/^[0-9+\- ]+$/.test(val)) {
                setInvalid(input, 'Invalid contact number format. Only numbers, +, -, and spaces are allowed.');
            }
            // Check for minimum length
            else if (digitsOnly.length < 7) {
                setInvalid(input, 'Contact number must have at least 7 digits');
            }
            // Check for maximum length
            else if (digitsOnly.length > 15) {
                setInvalid(input, 'Contact number cannot exceed 15 digits');
            }
            // Philippine mobile number validation
            else if (digitsOnly.length === 11 && digitsOnly.startsWith('09')) {
                // Valid Philippine mobile number format (09XXXXXXXXX)
                setValid(input);
            }
            // Philippine mobile number with country code
            else if (digitsOnly.length === 13 && digitsOnly.startsWith('639')) {
                // Valid Philippine mobile number with country code (639XXXXXXXXX)
                setValid(input);
            }
            // Philippine landline validation
            else if (digitsOnly.length >= 7 && digitsOnly.length <= 8 && !digitsOnly.startsWith('09')) {
                // Valid Philippine landline format
                setValid(input);
            }
            // International format validation
            else if (digitsOnly.length >= 10 && digitsOnly.length <= 15 && !digitsOnly.startsWith('09') && !digitsOnly.startsWith('639')) {
                // Valid international number format
                setValid(input);
            }
            // Invalid Philippine mobile number
            else if (digitsOnly.length === 11 && !digitsOnly.startsWith('09')) {
                setInvalid(input, 'Invalid Philippine mobile number. Must start with 09 (e.g., 09171234567)');
            }
            // Invalid Philippine mobile number with country code
            else if (digitsOnly.length === 13 && !digitsOnly.startsWith('639')) {
                setInvalid(input, 'Invalid Philippine mobile number with country code. Must start with 639 (e.g., 639171234567)');
            }
            // Other invalid formats
            else {
                setInvalid(input, 'Invalid contact number format. Please use valid Philippine mobile (09XXXXXXXXX), landline, or international format.');
            }
        } else {
            clearValidation(input);
        }
    });

    // Latitude
    $('#latitude').on('input blur', function () {
        const input = $(this);
        const val = parseFloat(input.val());
        if (input.val() && (isNaN(val) || val < -90 || val > 90)) {
            setInvalid(input, 'Please enter a valid latitude (-90 to 90)');
        } else {
            clearValidation(input);
            if (input.val()) setValid(input);
        }
    });

    // Longitude
    $('#longitude').on('input blur', function () {
        const input = $(this);
        const val = parseFloat(input.val());
        if (input.val() && (isNaN(val) || val < -180 || val > 180)) {
            setInvalid(input, 'Please enter a valid longitude (-180 to 180)');
        } else {
            clearValidation(input);
            if (input.val()) setValid(input);
        }
    });

    // Address (autocomplete already present, but add real-time validation)
    $('#address').on('input blur', function () {
        const input = $(this);
        if (!input.val().trim()) {
            setInvalid(input, 'Please provide an address');
        } else if (input.val().trim().length < 10) {
            setInvalid(input, 'Please provide a more detailed address (at least 10 characters)');
        } else {
            setValid(input);
        }
    });

    // Safety Features Validation with Smart Recommendations
    function validateSafetyFeatures() {
        const buildingType = $('#building_type').val();
        const totalFloors = parseInt($('#total_floors').val()) || 0;
        const recommendations = [];
        const warnings = [];

        // Building type specific recommendations
        if (buildingType === 'residential' && totalFloors >= 3) {
            if (!$('#has_fire_alarm').is(':checked')) {
                recommendations.push('Fire alarm system recommended for multi-story residential buildings');
            }
            if (!$('#has_emergency_exits').is(':checked')) {
                recommendations.push('Marked emergency exits required for buildings with 3+ floors');
            }
        }

        if (buildingType === 'commercial' || buildingType === 'industrial') {
            if (!$('#has_sprinkler_system').is(':checked')) {
                recommendations.push('Sprinkler system highly recommended for commercial/industrial buildings');
            }
            if (!$('#has_fire_alarm').is(':checked')) {
                warnings.push('Fire alarm system is typically required for commercial buildings');
            }
            if (!$('#has_fire_extinguishers').is(':checked')) {
                warnings.push('Fire extinguishers are mandatory for commercial buildings');
            }
        }

        if (buildingType === 'industrial') {
            if (!$('#has_emergency_lighting').is(':checked')) {
                recommendations.push('Emergency lighting is crucial for industrial facilities');
            }
        }

        // Floor-based recommendations
        if (totalFloors >= 5) {
            if (!$('#has_fire_escape').is(':checked')) {
                recommendations.push('Fire escape recommended for buildings with 5+ floors');
            }
        }

        // Display recommendations
        const safetyContainer = $('#safety-features-container');
        let feedbackHtml = '';

        if (recommendations.length > 0) {
            feedbackHtml += '<div class="alert alert-info mt-2"><strong>Recommendations:</strong><ul class="mb-0">';
            recommendations.forEach(rec => {
                feedbackHtml += `<li>${rec}</li>`;
            });
            feedbackHtml += '</ul></div>';
        }

        if (warnings.length > 0) {
            feedbackHtml += '<div class="alert alert-warning mt-2"><strong>Important:</strong><ul class="mb-0">';
            warnings.forEach(warn => {
                feedbackHtml += `<li>${warn}</li>`;
            });
            feedbackHtml += '</ul></div>';
        }

        // Remove existing feedback and add new one
        safetyContainer.find('.alert').remove();
        if (feedbackHtml) {
            safetyContainer.append(feedbackHtml);
        }
    }

    // Trigger safety features validation when relevant fields change
    $('#building_type, #total_floors').on('change', validateSafetyFeatures);
    $('input[name^="has_"]').on('change', validateSafetyFeatures);

    // Cross-field validation for building type and safety features
    function validateBuildingTypeCompliance() {
        const buildingType = $('#building_type').val();
        const totalFloors = parseInt($('#total_floors').val()) || 0;
        const buildingArea = parseFloat($('#building_area').val()) || 0;
        const complianceIssues = [];

        // High-rise building requirements (5+ floors)
        if (totalFloors >= 5) {
            if (!$('#has_fire_alarm').is(':checked')) {
                complianceIssues.push('Fire alarm system is required for buildings with 5+ floors');
            }
            if (!$('#has_emergency_exits').is(':checked')) {
                complianceIssues.push('Marked emergency exits are required for high-rise buildings');
            }
            if (!$('#has_emergency_lighting').is(':checked')) {
                complianceIssues.push('Emergency lighting is required for high-rise buildings');
            }
        }

        // Large building requirements (1000+ sqm)
        if (buildingArea >= 1000) {
            if (!$('#has_sprinkler_system').is(':checked')) {
                complianceIssues.push('Sprinkler system is required for buildings over 1000 sqm');
            }
            if (!$('#has_fire_extinguishers').is(':checked')) {
                complianceIssues.push('Fire extinguishers are required for large buildings');
            }
        }

        // Building type specific requirements
        if (buildingType === 'industrial') {
            if (buildingArea >= 500 && !$('#has_sprinkler_system').is(':checked')) {
                complianceIssues.push('Industrial buildings over 500 sqm require sprinkler systems');
            }
            if (!$('#has_emergency_lighting').is(':checked')) {
                complianceIssues.push('Emergency lighting is mandatory for industrial facilities');
            }
        }

        if (buildingType === 'commercial') {
            if (totalFloors >= 3 && !$('#has_fire_alarm').is(':checked')) {
                complianceIssues.push('Commercial buildings with 3+ floors require fire alarm systems');
            }
        }

        // Display compliance issues
        const complianceContainer = $('#compliance-container');
        let complianceHtml = '';

        if (complianceIssues.length > 0) {
            complianceHtml += '<div class="alert alert-danger mt-2"><strong>Compliance Issues:</strong><ul class="mb-0">';
            complianceIssues.forEach(issue => {
                complianceHtml += `<li>${issue}</li>`;
            });
            complianceHtml += '</ul></div>';
        }

        // Remove existing compliance feedback and add new one
        complianceContainer.find('.alert').remove();
        if (complianceHtml) {
            complianceContainer.append(complianceHtml);
        }
    }

    // Trigger compliance validation when relevant fields change
    $('#building_type, #total_floors, #building_area').on('change input', validateBuildingTypeCompliance);
    $('input[name^="has_"]').on('change', validateBuildingTypeCompliance);

    // Enhanced Last Inspection Date Validation
    $('#last_inspected').on('change blur', function () {
        const input = $(this);
        const selectedDate = new Date(input.val());
        const today = new Date();
        const oneYearAgo = new Date();
        oneYearAgo.setFullYear(today.getFullYear() - 1);
        const twoYearsAgo = new Date();
        twoYearsAgo.setFullYear(today.getFullYear() - 2);

        if (input.val()) {
            if (selectedDate > today) {
                setInvalid(input, 'Inspection date cannot be in the future');
            } else if (selectedDate < twoYearsAgo) {
                setInvalid(input, 'Inspection date is more than 2 years old. Consider scheduling a new inspection.');
            } else if (selectedDate < oneYearAgo) {
                // Show warning but don't invalidate
                input.removeClass('is-invalid').addClass('is-valid');
                input.siblings('.invalid-feedback').hide();
                
                // Show warning message
                const warningMsg = 'Inspection is over 1 year old. Annual inspections are recommended.';
                let warningDiv = input.siblings('.inspection-warning');
                if (warningDiv.length === 0) {
                    warningDiv = $('<div class="inspection-warning text-warning small mt-1"></div>');
                    input.after(warningDiv);
                }
                warningDiv.text(warningMsg).show();
            } else {
                setValid(input);
                input.siblings('.inspection-warning').hide();
            }
        } else {
            clearValidation(input);
            input.siblings('.inspection-warning').hide();
        }
    });

    // Trigger area validation when building type changes
    $('#building_type').on('change', function() {
        $('#building_area').trigger('blur');
    });

    // Trigger floor validation when building type or area changes
    $('#building_type, #building_area').on('change input', function() {
        $('#total_floors').trigger('blur');
    });

    // On form submit, trigger validation for all fields
    $('#buildingForm').on('submit', function (e) {
        let valid = true;
        let errorMessages = [];
        
        // Validate all required fields
        const requiredFields = [
            { id: 'building_name', name: 'Building Name' },
            { id: 'building_type', name: 'Building Type' },
            { id: 'total_floors', name: 'Total Floors' },
            { id: 'latitude', name: 'Latitude' },
            { id: 'longitude', name: 'Longitude' },
            { id: 'geo_fence_id', name: 'Geo-Fence Area' },
            { id: 'barangay_id', name: 'Barangay' }
        ];
        
        // Check required fields
        requiredFields.forEach(field => {
            const value = $(`#${field.id}`).val();
            if (!value || (field.id === 'total_floors' && parseInt(value) < 1)) {
                errorMessages.push(`${field.name} is required`);
                $(`#${field.id}`).addClass('is-invalid');
                valid = false;
            }
        });
        
        // Trigger validation for all fields
        $('#building_name, #building_type, #total_floors, #construction_year, #building_area, #contact_number, #contact_person, #latitude, #longitude, #address, #last_inspected').each(function () {
            $(this).trigger('blur');
            if ($(this).hasClass('is-invalid')) valid = false;
        });
        
        // Check for compliance issues
        validateBuildingTypeCompliance();
        const complianceIssues = $('#compliance-container .alert-danger').length;
        
        if (!valid || complianceIssues > 0) {
            e.preventDefault();
            
            if (errorMessages.length > 0) {
                Swal.fire({
                    title: 'Required Fields Missing',
                    html: `Please fill in the following required fields:<br>• ${errorMessages.join('<br>• ')}`,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } else if (complianceIssues > 0) {
                Swal.fire({
                    title: 'Compliance Issues',
                    text: 'Please address the compliance issues before submitting the form.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
            }
            
            // Scroll to first error
            const firstError = $('.is-invalid').first();
            if (firstError.length) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 300);
                firstError.focus();
            }
        }
    });
}); 