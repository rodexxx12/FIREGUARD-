<?php include '../../components/header.php'; ?>
    <link rel="stylesheet" href="../css/device_preview.css">
    <style>
    /* Burger Menu Toggle Button Styles - Green with Animations */
    .filter-toggle-btn {
        display: flex !important;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: auto;
        height: auto;
        background: rgba(38, 185, 154, 0.08);
        border: 1px solid rgba(38, 185, 154, 0.2);
        cursor: pointer;
        padding: 6px 8px;
        margin: 0;
        transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        visibility: visible !important;
        opacity: 1 !important;
        border-radius: 6px;
        position: relative;
        line-height: 1;
        box-shadow: 0 2px 4px rgba(38, 185, 154, 0.15);
    }
    
    .filter-toggle-btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: rgba(38, 185, 154, 0.15);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        transition: all 0.4s ease;
    }
    
    .filter-toggle-btn:hover {
        transform: scale(1.1);
        background: rgba(38, 185, 154, 0.15);
        border-color: rgba(38, 185, 154, 0.4);
        box-shadow: 0 3px 8px rgba(38, 185, 154, 0.25);
    }
    
    .filter-toggle-btn:hover::before {
        width: 100%;
        height: 100%;
    }
    
    .filter-toggle-btn.active {
        transform: scale(1.1) rotate(90deg);
        background: rgba(38, 185, 154, 0.2);
        border-color: rgba(38, 185, 154, 0.5);
        box-shadow: 0 3px 10px rgba(38, 185, 154, 0.3);
    }
    
    .filter-toggle-btn.active::before {
        width: 100%;
        height: 100%;
    }
    
    .burger-line {
        width: 22px;
        height: 3px;
        background-color: #26B99A;
        border-radius: 3px;
        transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        display: block;
        margin: 2.5px 0;
        position: relative;
        box-shadow: 0 2px 4px rgba(38, 185, 154, 0.4);
    }
    
    .filter-toggle-btn:hover .burger-line {
        background-color: #1e9d82;
        box-shadow: 0 3px 6px rgba(38, 185, 154, 0.5);
        transform: scaleX(1.1);
    }
    
    .filter-toggle-btn.active .burger-line {
        background-color: #26B99A;
        box-shadow: 0 3px 6px rgba(38, 185, 154, 0.6);
    }
    
    .filter-toggle-btn.active .burger-line:nth-child(1) {
        transform: rotate(45deg) translate(6px, 6px);
        background-color: #26B99A;
        width: 22px;
    }
    
    .filter-toggle-btn.active .burger-line:nth-child(2) {
        opacity: 0;
        transform: scaleX(0);
    }
    
    .filter-toggle-btn.active .burger-line:nth-child(3) {
        transform: rotate(-45deg) translate(6px, -6px);
        background-color: #26B99A;
        width: 22px;
    }
    
    /* Pulse animation when active */
    @keyframes pulse-green {
        0% {
            box-shadow: 0 0 0 0 rgba(38, 185, 154, 0.4);
        }
        50% {
            box-shadow: 0 0 0 8px rgba(38, 185, 154, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(38, 185, 154, 0);
        }
    }
    
    .filter-toggle-btn.active {
        animation: pulse-green 2s infinite;
    }
    
    /* Filter Overlay with Animation */
    .filter-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .filter-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    
    /* Filter Panel - Side Panel Style with Enhanced Animations */
    .filter-panel {
        position: fixed;
        top: 0;
        right: -400px;
        width: 380px;
        height: 100%;
        background-color: #fff;
        box-shadow: -2px 0 20px rgba(0,0,0,0.3);
        z-index: 1000;
        transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto;
        padding: 2rem;
        transform: translateX(0);
    }
    
    .filter-panel.active {
        right: 0;
        animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    @keyframes slideInRight {
        0% {
            transform: translateX(100%);
            opacity: 0;
        }
        60% {
            transform: translateX(-5%);
        }
        100% {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    /* Filter Panel Header Animation */
    .filter-panel-header {
        animation: fadeInDown 0.5s ease 0.2s both;
    }
    
    @keyframes fadeInDown {
        0% {
            opacity: 0;
            transform: translateY(-20px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Filter Groups Staggered Animation */
    .filter-panel.active .filter-group {
        animation: fadeInUp 0.5s ease both;
    }
    
    .filter-panel.active .filter-group:nth-child(1) {
        animation-delay: 0.1s;
    }
    
    .filter-panel.active .filter-group:nth-child(2) {
        animation-delay: 0.15s;
    }
    
    .filter-panel.active .filter-group:nth-child(3) {
        animation-delay: 0.2s;
    }
    
    .filter-panel.active .filter-group:nth-child(4) {
        animation-delay: 0.25s;
    }
    
    .filter-panel.active .filter-group:nth-child(5) {
        animation-delay: 0.3s;
    }
    
    @keyframes fadeInUp {
        0% {
            opacity: 0;
            transform: translateY(20px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .filter-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e0e0e0;
    }
    
    .filter-panel-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
    }
    
    .filter-panel-body {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .filter-group label {
        font-weight: 600;
        color: #555;
        font-size: 0.95rem;
    }
    
    .filter-group .form-control,
    .filter-group .form-select,
    .filter-group .btn {
        width: 100%;
    }
    
    .shortcut-hint {
        font-size: 0.75rem;
        color: #999;
        font-weight: normal;
    }
    
    @media (max-width: 768px) {
        .filter-panel {
            width: 100%;
            right: -100%;
        }
    }
    
    .panel_toolbox {
        float: right;
        margin: 0;
        list-style: none;
        padding: 0;
        min-width: 70px;
        display: flex;
        align-items: center;
    }
    
    .panel_toolbox li {
        float: left;
        cursor: pointer;
        margin-left: 5px;
        display: flex;
        align-items: center;
        min-height: 32px;
    }
    
    .panel_toolbox li a {
        padding: 5px;
        color: #C5C7CB;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        height: 100%;
    }
    
    .panel_toolbox li a:hover {
        color: #26B99A;
    }
    
    /* Ensure burger toggle is always visible and aligned */
    #filterToggleBtn {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        position: relative;
        z-index: 10;
        align-items: center;
        justify-content: center;
        min-height: 32px;
    }
    
    .panel_toolbox #filterToggleBtn {
        display: flex !important;
        align-items: center;
        justify-content: center;
    }
    
    /* Make sure the burger lines are visible and green */
    #filterToggleBtn .burger-line {
        background-color: #26B99A !important;
        display: block !important;
        visibility: visible !important;
        width: 22px !important;
        height: 3px !important;
    }
    
    #filterToggleBtn:hover .burger-line {
        background-color: #1e9d82 !important;
        transform: scaleX(1.1);
    }
    
    /* Add smooth transitions to filter inputs */
    .filter-group .form-control,
    .filter-group .form-select {
        transition: all 0.3s ease;
        border: 1px solid #ddd;
    }
    
    .filter-group .form-control:focus,
    .filter-group .form-select:focus {
        border-color: #26B99A;
        box-shadow: 0 0 0 3px rgba(38, 185, 154, 0.1);
        transform: translateY(-1px);
    }
    
    /* Animate buttons on hover */
    .filter-group .btn {
        transition: all 0.3s ease;
    }
    
    .filter-group .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .filter-group .btn:active {
        transform: translateY(0);
    }
    </style>
</head>
<body class="nav-md">
    <div class="container body">
      <div class="main_container"> 