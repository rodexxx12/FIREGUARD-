<main class="main-content">
        <div class="row">
                    <!-- Main Map Column -->
                    <div class="col-lg-8">
                        <div class="control-panel">
                            <div id="map"></div>
                            <div class="d-flex justify-content-between mt-3">
                                <div class="btn-group" role="group">
                                    <button id="routeToStation" class="btn btn-emergency">
                                        <i class="bi bi-truck me-1"></i> Emergency Route
                                    </button>
                                    <button id="clearRoute" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-1"></i> Clear
                                    </button>
                                </div>
                                <div>
                                <button id="locate-emergency" onclick="locateEmergency()" class="btn btn-primary">
                                    <i class="bi bi-geo me-1"></i> Locate Device
                                </button>
                                <button id="toggle-buildings" class="btn btn-outline-primary">
                            <i class="bi bi-eye-slash me-1"></i> Show Buildings
                        </button>
                        </div>
                        <!-- Speech controls removed - automatic speech only -->
                    </div>
                </div>
            </div>
