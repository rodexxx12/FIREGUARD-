<!-- Add Device Modal -->
<div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDeviceModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Add New Fire Detection Device
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addDeviceForm" method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="device_number" class="form-label">Device Number *</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="device_number" name="device_number" placeholder="Enter Device Number" required>
                                    <button type="button" class="btn btn-outline-secondary" id="generateDeviceBtn">
                                        <i class="fas fa-magic"></i> Generate
                                    </button>
                                </div>
                                <div class="invalid-feedback" id="device_number_feedback"></div>
                                <div class="form-text">Format: DV1-PHI-[UNIQUE_ID] (e.g., DV1-PHI-000345)</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="serial_number" class="form-label">Serial Number *</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="serial_number" name="serial_number" placeholder="Enter Serial Number" required>
                                    <button type="button" class="btn btn-outline-secondary" id="generateSerialBtn">
                                        <i class="fas fa-magic"></i> Generate
                                    </button>
                                </div>
                                <div class="invalid-feedback" id="serial_number_feedback"></div>
                                <div class="form-text">Format: SEN-[YYWW]-[SERIAL] (e.g., SEN-2519-005871)</div>
                            </div>
                            
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-primary" id="generateBothBtn">
                                    <i class="fas fa-random"></i> Generate Both
                                </button>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Device Type</label>
                                <input type="text" class="form-control" value="Fire Detection Device" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="approved">Approved</option>
                                    <option value="pending">Pending</option>
                                    <option value="deactivated">Deactivated</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Device Information Preview</label>
                                <div id="deviceInfoContainer" class="text-center" style="min-height: 200px; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 1rem; background-color: #f8f9fa;">
                                    <div class="text-muted">
                                        <i class="fas fa-info-circle fa-3x mb-2"></i>
                                        <p>Device information will be displayed here</p>
                                        <small>Enter device number and serial number to see preview</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addDeviceForm" class="btn btn-primary" id="addDeviceBtn">
                    <i class="fas fa-save"></i> Add Device
                </button>
            </div>
        </div>
    </div>
</div> 