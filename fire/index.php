<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIREGUARD</title>
    <link href="images/logo1.png" rel="icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #ff5a4d; /* slightly lighter primary */
            --secondary-color: #ff814f;
            --accent-color: #ffbf40;
            --dark-bg: #121735; /* lighter than previous */
            --darker-bg: #0b1030; /* lighter than previous */
            --light-text: #f3f6fb; /* soft off-white */
            --gray-text: #c9cfdb; /* lighter gray */
            --card-bg: rgba(255, 255, 255, 0.12); /* slightly brighter glass */
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--darker-bg) 0%, var(--dark-bg) 100%);
            color: var(--light-text);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Background video */
        .bg-video-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            z-index: -3;
        }

        .bg-video-container video {
            position: absolute;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            transform: translate(-50%, -50%);
            object-fit: cover;
            filter: brightness(0.5) contrast(1.05) saturate(1.05);
            opacity: 1;
        }

        /* Subtle overlay to keep text readable on lighter theme */
        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(
                180deg,
                rgba(10, 14, 39, 0.45) 0%,
                rgba(10, 14, 39, 0.55) 50%,
                rgba(10, 14, 39, 0.65) 100%
            );
            z-index: -2;
            pointer-events: none;
        }

        /* Inline Login Panel (pops in on the right) */
        .inline-login-panel {
            position: absolute;
            right: 2rem;
            top: 55%;
            transform: translateY(-50%) scale(0.8);
            opacity: 0;
            max-width: 520px;
            width: 92vw;
            background: rgba(255, 255, 255, 0.10);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 18px;
            box-shadow: 0 12px 50px rgba(0, 0, 0, 0.35);
            padding: 2rem;
            z-index: 5;
            transition: transform 400ms cubic-bezier(0.34, 1.56, 0.64, 1), opacity 400ms ease-out;
            will-change: transform, opacity;
        }

        .inline-login-panel.active {
            transform: translateY(-50%) scale(1);
            opacity: 1;
        }

        /* Inline Register Panel */
        .inline-register-panel {
            position: fixed;
            inset: 0;
            transform: scale(0.96);
            opacity: 0;
            background: rgba(9, 13, 36, 0.92);
            backdrop-filter: blur(24px);
            z-index: 1100;
            transition: transform 400ms cubic-bezier(0.34, 1.56, 0.64, 1), opacity 320ms ease-out;
            will-change: transform, opacity;
            pointer-events: none;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 8rem 1.5rem 0.2rem;
        }

        .inline-register-panel.active {
            transform: scale(1);
            opacity: 1;
            pointer-events: all;
        }

        .inline-register-content {
            max-width: 1200px;
            width: 100%;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 22px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
            padding: 0.9rem 2.75rem 0.8rem;
            position: relative;
        }

        .inline-register-header {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            margin-bottom: 2rem;
            padding-top: 1rem;
            gap: 1rem;
        }

        .register-header-left {
            display: flex;
            align-items: center;
        }

        .register-header-center {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .register-header-right {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        .inline-register-title {
            font-size: 1.55rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }

        .inline-register-close {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: var(--light-text);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.2s ease;
        }

        .inline-register-close:hover {
            background: rgba(66, 133, 244, 0.3);
            transform: rotate(90deg);
        }

        .inline-register-subtitle {
            font-size: 0.95rem;
            color: var(--gray-text);
            margin-bottom: 0.7rem;
            line-height: 1.4;
        }

        /* Progress Indicator */
        .register-progress {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.75rem;
            padding: 0;
            margin: 0;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }

        .progress-step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: var(--gray-text);
            transition: all 0.3s ease;
        }

        .progress-step.active .progress-step-number {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 0 15px rgba(255, 68, 68, 0.4);
        }

        .progress-step.completed .progress-step-number {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-color: var(--primary-color);
            color: white;
        }

        .progress-connector {
            width: 60px;
            height: 2px;
            background: rgba(255, 255, 255, 0.2);
            margin-top: -20px;
        }

        .progress-step.active + .progress-connector {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .progress-step.completed + .progress-connector {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        /* Email Verification Button */
        .email-verification-group {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .email-verification-group .inline-login-form-group {
            flex: 1;
        }

        .email-input-group {
            position: relative;
        }

        .send-verification-btn {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: white;
            border: none;
            padding: 0.95rem 1.5rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0;
            height: fit-content;
        }

        .send-verification-btn .send-verification-label {
            display: inline-block;
        }

        .send-verification-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 129, 79, 0.4);
        }

        .verification-instruction {
            font-size: 0.5rem;
            color: var(--gray-text);
            margin: 0;
            margin-bottom: 0.25rem;
            margin-left: 1.1rem;
            line-height: 1.2;
            position: absolute;
            top: -1.2rem;
            left: 0;
        }

        /* Registration Steps */
        .register-step {
            display: none;
            flex-direction: column;
            gap: 2rem;
        }

        .register-step.active {
            display: flex;
        }

        /* Step 2: Location with Map Layout */
        .register-step.location-step {
            flex-direction: row;
            gap: 2rem;
            align-items: stretch;
        }

        .location-map-container {
            flex: 1.4;
            min-height: 400px;
            border-radius: 16px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        #locationMap {
            width: 100%;
            height: 100%;
            min-height: 400px;
            border-radius: 16px;
        }

        .current-location-btn {
            position: absolute;
            bottom: 15px;
            left: 15px;
            z-index: 1000;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .current-location-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 68, 68, 0.4);
        }

        .current-location-btn:active {
            transform: translateY(0);
        }

        .current-location-btn svg {
            width: 18px;
            height: 18px;
        }

        .location-form-container {
            flex: 0.9;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Validated Field Styles */
        .validated-field {
            position: relative;
        }

        .validated-field .auth-input {
            background: rgba(255, 255, 255, 0.12);
        }

        .validated-field .auth-input[readonly] {
            cursor: not-allowed;
            background: rgba(255, 255, 255, 0.08);
        }

        .field-checkmark {
            position: absolute;
            bottom: 0.75rem;
            right: 1rem;
            color: #4caf50;
            font-size: 1.1rem;
            font-weight: bold;
            z-index: 2;
            pointer-events: none;
        }

        .validated-field .address-textarea + label + .field-checkmark {
            bottom: 0.5rem;
        }

        .validation-message {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .validation-message.success {
            color: #4caf50;
        }

        .field-info {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .field-info svg {
            flex-shrink: 0;
            margin-top: 0.1rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .field-helper-text {
            margin-top: 0.25rem;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .address-textarea {
            min-height: 80px;
            resize: vertical;
            padding: 0.95rem 2.5rem 0.95rem 1.1rem !important;
            font-family: inherit;
        }

        .address-textarea:focus,
        .address-textarea.has-value {
            padding-top: 1.3rem !important;
            padding-bottom: 0.65rem !important;
        }

        @media (max-width: 968px) {
            .register-step.location-step {
                flex-direction: column;
            }

            .location-map-container {
                min-height: 300px;
            }

            #locationMap {
                min-height: 300px;
            }
        }

        body.register-active .hero-content {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        body.register-active .inline-login-panel {
            pointer-events: none;
        }

        body.register-active {
            overflow: hidden;
        }

        body.register-active .services,
        body.register-active .stats,
        body.register-active footer {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .inline-register-actions {
            margin-top: 0.4rem;
            text-align: center;
            font-size: 0.88rem;
            color: var(--gray-text);
            display: inline-flex;
            align-items: baseline;
            justify-content: center;
            gap: 0.35rem;
            white-space: nowrap;
        }

        .inline-register-actions button {
            background: none;
            border: none;
            color: var(--primary-color);
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            margin: 0;
            transition: color 0.2s ease, text-decoration 0.2s ease;
            -webkit-tap-highlight-color: rgba(255, 68, 68, 0.2);
            touch-action: manipulation;
            line-height: 1.2;
            font-size: inherit;
        }

        .inline-register-actions button:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        /* Hide "Already have an account?" on Step 2 */
        #registerStep2.active ~ .inline-register-actions,
        .register-step.location-step.active ~ .inline-register-actions {
            display: none;
        }

        /* Hide "Already have an account?" on Step 3 */
        #registerStep3.active ~ .inline-register-actions {
            display: none;
        }

        /* Step 3: Device Registration Styles */
        .device-registration-title {
            font-size: 1.8rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--light-text);
        }

        .device-registration-banner {
            background: rgba(100, 181, 246, 0.15);
            border: 1px solid rgba(100, 181, 246, 0.3);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 0;
            color: var(--light-text);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-helper-text {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 0.5rem;
            margin-left: 1.1rem;
        }

        .device-registration-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 0.75rem;
        }

        .device-registration-actions .back-btn {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .device-registration-actions .back-btn:hover {
            background: rgba(255, 68, 68, 0.1);
        }

        .inline-login-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .inline-login-title {
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .inline-login-close {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: var(--light-text);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.2s ease;
        }

        .inline-login-close:hover {
            background: rgba(255, 68, 68, 0.3);
            transform: rotate(90deg);
        }

        .inline-login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-top: 0;
            margin-bottom: 0.5rem;
        }

        .inline-login-form-group {
            position: relative;
            display: flex;
            flex-direction: column;
            --label-gap-right: 0px;
        }

        .inline-login-form-group::after {
            content: '';
            position: absolute;
            top: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.18);
            opacity: 0;
            transition: opacity 0.3s ease, background 0.3s ease;
            pointer-events: none;
        }

        .inline-login-form-group::after {
            right: 0;
            width: var(--label-gap-right, 0px);
        }

        .inline-login-form-group.label-raised::after {
            opacity: 1;
        }

        .inline-login-form-group.label-focused::after {
            background: var(--primary-color);
        }

        .inline-login-form label {
            position: absolute;
            left: 1.1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.65);
            font-weight: 400;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: transparent;
            z-index: 1;
        }

        .inline-login-form input[type="text"],
        .inline-login-form input[type="password"],
        .inline-login-form input[type="email"],
        .inline-login-form input[type="number"],
        .inline-login-form textarea {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: var(--light-text);
            padding: 0.95rem 1.1rem;
            border-radius: 12px;
            outline: none;
            font-size: 1rem;
            transition: all 0.2s ease;
            width: 100%;
        }

        .inline-login-form textarea {
            min-height: 80px;
            resize: vertical;
            font-family: inherit;
        }

        .inline-login-form input::placeholder,
        .inline-login-form textarea::placeholder {
            color: transparent;
        }

        .inline-login-form input:focus,
        .inline-login-form input.has-value,
        .inline-login-form textarea:focus,
        .inline-login-form textarea.has-value {
            padding-top: 1rem;
            padding-bottom: 0.65rem;
            border-top-color: transparent;
        }

        .inline-login-form input:focus + label,
        .inline-login-form input.has-value + label,
        .inline-login-form textarea:focus + label,
        .inline-login-form textarea.has-value + label {
            top: -0.6rem;
            transform: none;
            font-size: 0.75rem;
            color: var(--primary-color);
            font-weight: 500;
            left: 1.1rem;
        }

        .inline-login-form textarea + label {
            top: 1.1rem;
        }

        .inline-login-form input:focus {
            border-color: var(--primary-color);
            border-top-color: transparent;
            background: rgba(255, 255, 255, 0.16);
            box-shadow: 0 0 0 3px rgba(255, 68, 68, 0.14);
        }

        /* Calendar Icon Button */
        .calendar-icon-btn {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--gray-text);
            cursor: pointer;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            z-index: 2;
        }

        .calendar-icon-btn:hover {
            color: var(--primary-color);
            transform: translateY(-50%) scale(1.1);
        }

        .calendar-icon-btn svg {
            pointer-events: none;
        }

        .birthdate-input-group input {
            padding-right: 3rem;
            cursor: pointer;
        }

        .inline-login-submit {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 0.95rem 1.6rem;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.4rem;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(255, 68, 68, 0.28);
        }

        .inline-login-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(255, 68, 68, 0.38);
        }

        .inline-login-register {
            margin-top: 0.85rem;
            margin-bottom: -0.3rem;
            font-size: 0.88rem;
            color: var(--gray-text);
            text-align: center;
        }

        .inline-login-register a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease, text-decoration 0.2s ease;
            display: inline-block;
            padding: 0.5rem 0.75rem;
            margin: -0.5rem -0.75rem;
            -webkit-tap-highlight-color: rgba(255, 68, 68, 0.2);
            cursor: pointer;
            touch-action: manipulation;
        }

        .inline-login-register a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        /* Smooth base transition for hero content */
        .hero-content {
            transition: transform 1100ms cubic-bezier(0.25, 0.8, 0.25, 1), opacity 1100ms cubic-bezier(0.25, 0.8, 0.25, 1);
            will-change: transform, opacity;
        }

        /* Hero content slide out (to the left) */
        .hero-content.slide-left {
            transform: translateX(-35%);
            opacity: 1;
        }

        @media (max-width: 768px) {
            /* Hide hero content on mobile - show login directly */
            .hero-content {
                display: none !important;
            }
            
            .inline-login-panel,
            .inline-register-panel {
                right: 1rem;
                left: 1rem;
                width: auto;
                max-width: none;
            }
            
            /* Hide close button on mobile for direct login experience */
            .inline-login-close {
                display: none;
            }
            
            /* On mobile, hide hero content when login is opened */
            .hero-content.slide-left {
                transform: translateX(0);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
            }
        }

        /* Animated background particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 50%;
            animation: float 15s infinite ease-in-out;
            opacity: 0.3;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); }
            25% { transform: translateY(-100px) translateX(50px); }
            50% { transform: translateY(-200px) translateX(-50px); }
            75% { transform: translateY(-100px) translateX(100px); }
        }

        /* Navigation */
        nav {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(10, 14, 39, 0.9);
            backdrop-filter: blur(10px);
            padding: 1.5rem 5%;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 30px rgba(255, 68, 68, 0.2);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-left: 3rem;
        }

        .logo-icon-wrapper {
            width: 50px;
            height: 50px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon-wrapper::before {
            content: '';
            position: absolute;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
            animation: ripple 2s infinite;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            transform-origin: center center;
            z-index: 1;
        }

        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            position: relative;
            z-index: 2;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            box-sizing: border-box;
        }

        @keyframes ripple {
            0% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
            100% {
                transform: translate(-50%, -50%) scale(1.5);
                opacity: 0;
            }
        }

        @keyframes rippleMobile {
            0% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 0.6;
            }
            100% {
                transform: translate(-50%, -50%) scale(1.6);
                opacity: 0;
            }
        }

        @keyframes rippleMobileSmall {
            0% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 0.5;
            }
            100% {
                transform: translate(-50%, -50%) scale(1.5);
                opacity: 0;
            }
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: bold;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 2px;
        }

        .nav-links {
            display: flex;
            gap: 1.9rem;
            list-style: none;
        }

        .nav-links a {
            color: var(--light-text);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            position: relative;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        .nav-links a.active {
            color: var(--primary-color);
        }

        .nav-links a.active::after {
            width: 100%;
        }

        /* Nav Burger Menu */
        .nav-burger {
            display: none;
            flex-direction: column;
            gap: 5px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 5px;
            z-index: 1001;
        }

        .nav-burger span {
            width: 25px;
            height: 3px;
            background: var(--light-text);
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .nav-burger.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }

        .nav-burger.active span:nth-child(2) {
            opacity: 0;
        }

        .nav-burger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        /* Mobile Navigation */
        .mobile-nav {
            position: fixed;
            top: calc(var(--nav-height, 80px));
            left: 0;
            width: 100%;
            max-height: 0;
            background: rgba(10, 14, 39, 0.98);
            backdrop-filter: blur(10px);
            z-index: 999;
            transition: max-height 1s cubic-bezier(0.25, 0.1, 0.25, 1), opacity 1s cubic-bezier(0.25, 0.1, 0.25, 1), padding 1s cubic-bezier(0.25, 0.1, 0.25, 1);
            padding-top: 0;
            padding-bottom: 0;
            margin-top: 0;
            overflow: hidden;
            opacity: 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.5);
            will-change: max-height, opacity;
        }

        .mobile-nav.active {
            max-height: 500px;
            padding-top: 0;
            padding-bottom: 0;
            opacity: 1;
        }

        .mobile-nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .mobile-nav-links li {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .mobile-nav-links a {
            display: block;
            padding: 0.75rem 2rem;
            color: var(--light-text);
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .mobile-nav-links a:hover {
            color: var(--primary-color);
            background: rgba(255, 68, 68, 0.1);
            padding-left: 2.5rem;
        }

        .mobile-nav-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .mobile-nav-overlay.active {
            display: block;
            opacity: 1;
        }

        /* Hero Section */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
            z-index: 1;
        }

        .hero-content {
            max-width: 900px;
            animation: fadeInUp 1s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero h1 {
            font-size: 4.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--light-text) 0%, var(--primary-color) 50%, var(--accent-color) 100%);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
            text-shadow: 0 0 40px rgba(255, 68, 68, 0.3);
            animation: gradientShift 5s ease infinite;
        }

        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        .hero p {
            font-size: 1.3rem;
            color: var(--gray-text);
            margin-bottom: 2.5rem;
            line-height: 1.8;
        }

        .cta-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--light-text);
            box-shadow: 0 10px 30px rgba(255, 68, 68, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(255, 68, 68, 0.6);
        }

        .btn-secondary {
            background: transparent;
            color: var(--light-text);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            transform: translateY(-3px);
        }

        /* Floating Action Buttons */
        .floating-buttons-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            display: flex;
            flex-direction: column-reverse;
            align-items: flex-end;
        }

        .floating-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1rem;
            transition: transform 0.4s ease-out, opacity 0.3s ease-out;
        }

        .floating-buttons.hidden {
            transform: translateY(calc(100% + 80px));
            opacity: 0;
            pointer-events: none;
            margin-bottom: 0;
        }

        .floating-buttons:not(.hidden) {
            transform: translateY(0);
            opacity: 1;
            pointer-events: all;
        }

        .burger-menu-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: #ffffff;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1001;
        }

        .burger-menu-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(255, 68, 68, 0.5);
        }

        .burger-menu-btn span {
            width: 25px;
            height: 3px;
            background: #ffffff;
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .burger-menu-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }

        .burger-menu-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .burger-menu-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        .floating-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .floating-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .floating-btn-phone {
            background: #ff4444;
            color: #ffffff;
        }

        .floating-btn-contact {
            background: #4285f4;
            color: #ffffff;
        }

        .floating-btn-help {
            background: #34a853;
            color: #ffffff;
        }

        .floating-btn {
            font-size: 0;
        }

        .floating-btn::after {
            content: '';
            display: block;
            width: 28px;
            height: 28px;
            filter: brightness(0) invert(1);
        }

        .floating-btn-phone::after {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='white' d='M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        .floating-btn-contact::after {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='white' d='M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-1 13H5V7h14v10z'/%3E%3Cpath fill='white' d='M7.5 10c.8 0 1.5.7 1.5 1.5S8.3 13 7.5 13 6 12.3 6 11.5 6.7 10 7.5 10zm0 3.5c.6 0 1.1-.4 1.2-1h-2.4c.1.6.6 1 1.2 1zm4-3.5h7v1h-7zm0 2h7v1h-7zm0 2h5v1h-5z'/%3E%3Ccircle fill='white' cx='7.5' cy='11.5' r='1.5'/%3E%3Cpath fill='white' d='M6 14h3v1H6z'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        .floating-btn-help::after {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='white' d='M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7zm-1 12h-2v-1h2v1zm2-4c0 .83-.67 1.5-1.5 1.5h-1c-.83 0-1.5-.67-1.5-1.5v-2c0-.83.67-1.5 1.5-1.5h1c.83 0 1.5.67 1.5 1.5v2z'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        /* Emergency Contacts Modal */
        .emergency-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .emergency-modal.active {
            display: flex;
            opacity: 1;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-content {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 2.5rem;
            max-width: 800px;
            width: 100%;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideDown 0.4s ease-out;
        }

        .emergency-modal.active .modal-content {
            animation: slideDown 0.4s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--accent-color);
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            color: #ffffff;
            font-size: 2rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(90deg);
        }

        .modal-instruction {
            color: #ffffff;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .emergency-contacts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .contact-card {
            background: rgba(255, 255, 255, 0.05);
            border-left: 4px solid var(--primary-color);
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .contact-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255, 68, 68, 0.2);
        }

        .contact-card-title {
            color: var(--accent-color);
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.8rem;
        }

        .contact-card-description {
            color: #ffffff;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .contact-info {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .contact-icon {
            font-size: 1.5rem;
            filter: drop-shadow(0 0 3px rgba(255, 68, 68, 0.5));
        }

        /* Important Contacts Modal */
        .important-contacts-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .important-contacts-modal.active {
            display: flex;
            opacity: 1;
        }

        .important-modal-content {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 1.5rem;
            max-width: 900px;
            width: 100%;
            max-height: calc(100vh - 4rem);
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideDown 0.4s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
        }

        .important-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-shrink: 0;
        }

        .important-modal-title {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent-color);
            margin: 0;
        }

        .important-modal-subtitle {
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: normal;
            margin-top: 0.3rem;
            margin-bottom: 0;
        }

        .important-contacts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            flex: 1;
            overflow: hidden;
        }

        .important-contact-card {
            background: rgba(255, 255, 255, 0.05);
            border-left: 4px solid var(--primary-color);
            border-radius: 10px;
            padding: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .important-contact-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255, 68, 68, 0.2);
        }

        .important-contact-title {
            color: var(--accent-color);
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .important-contact-address,
        .important-contact-description {
            color: #ffffff;
            font-size: 0.8rem;
            line-height: 1.4;
            margin-bottom: 0.6rem;
        }

        .important-contact-number {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.4rem;
        }

        .important-contact-number a {
            color: #ffffff;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .important-contact-number a:hover {
            color: var(--primary-color);
        }

        .important-contact-icon {
            font-size: 1.2rem;
            display: inline-block;
            min-width: 20px;
        }

        /* Fire Safety Tips Modal */
        .fire-safety-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .fire-safety-modal.active {
            display: flex;
            opacity: 1;
        }

        .safety-modal-content {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 2.5rem;
            padding-top: 2rem;
            padding-bottom: 2rem;
            max-width: 900px;
            width: 100%;
            max-height: calc(100vh - 4rem);
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideDown 0.4s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .safety-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            flex-shrink: 0;
        }

        .safety-modal-title {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent-color);
            margin: 0;
        }

        .safety-modal-subtitle {
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: normal;
            margin-top: 0.3rem;
            margin-bottom: 0;
        }

        .safety-tips-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(3, auto);
            gap: 1.2rem;
            flex: 1;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            overflow: hidden;
        }

        .safety-tip-card {
            background: rgba(255, 255, 255, 0.05);
            border-left: 4px solid #34a853;
            border-radius: 10px;
            padding: 1.2rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .safety-tip-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(52, 168, 83, 0.2);
        }

        .safety-tip-title {
            color: #34a853;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.6rem;
        }

        .safety-tip-description {
            color: #ffffff;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        /* Services Section */
        .services {
            padding: 6rem 5%;
            position: relative;
            z-index: 1;
        }

        .section-title {
            text-align: center;
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-subtitle {
            text-align: center;
            color: var(--gray-text);
            font-size: 1.2rem;
            margin-bottom: 4rem;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .service-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 68, 68, 0.2);
            border-radius: 20px;
            padding: 1.6rem; /* tighter outer padding for more readable content area */
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 68, 68, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .service-card:hover::before {
            left: 100%;
        }

        .service-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary-color);
            box-shadow: 0 20px 40px rgba(255, 68, 68, 0.3);
        }

        .service-icon {
            font-size: 3.5rem;
            margin-bottom: 1.2rem;
            display: block;
        }

        .service-card h3 {
            font-size: 1.25rem;
            line-height: 1.3;
            margin-bottom: 0.6rem;
            color: var(--light-text);
        }

        .service-card p {
            color: var(--gray-text);
            line-height: 1.65;
            font-size: 0.98rem;
            word-break: break-word;
        }

        /* Stats Section */
        .stats {
            padding: 6rem 5%;
            background: linear-gradient(135deg, rgba(255, 68, 68, 0.35), rgba(255, 170, 0, 0.35));
            position: relative;
            z-index: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 3rem;
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .stat-item h2 {
            font-size: 3.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stat-item p {
            color: var(--gray-text);
            font-size: 1.1rem;
        }

        /* Footer */
        footer {
            padding: 4rem 5% 2rem;
            background: #000000;
            position: relative;
            z-index: 1;
            border-top: 1px solid rgba(255, 68, 68, 0.2);
        }

        .footer-main {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 3rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-column h3 {
            color: #ffffff;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .footer-column p {
            color: #ffffff;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .footer-column ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-column ul li {
            margin-bottom: 0.8rem;
        }

        .footer-column ul li a {
            color: #ffffff;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-block;
            transition: all 0.3s ease;
            transform: translateX(0);
        }

        .footer-column ul li a:hover {
            color: var(--primary-color);
            transform: translateX(10px);
        }

        .footer-column .contact-item {
            color: #ffffff;
            font-size: 0.9rem;
            margin-bottom: 0.8rem;
            line-height: 1.6;
        }

        .footer-column .contact-item a {
            color: #ffffff;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            transform: translateX(0);
        }

        .footer-column .contact-item a:hover {
            color: var(--primary-color);
            transform: translateX(10px);
        }

        .social-icons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .social-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .social-icon svg {
            width: 20px;
            height: 20px;
            fill: #ffffff;
        }

        .social-icon:hover {
            background: rgba(0, 0, 0, 0.8);
            transform: translateY(-3px);
        }

        .footer-bottom {
            display: flex;
            justify-content: center;
            align-items: center;
            padding-top: 1.5rem;
            text-align: center;
        }

        .footer-copyright {
            color: #ffffff;
            font-size: 0.9rem;
        }

        .footer-bottom-buttons {
            display: flex;
            gap: 1rem;
        }

        .footer-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            transition: transform 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        .footer-btn:hover {
            transform: scale(1.1);
        }

        .footer-btn-phone {
            background: #ff4444;
            color: #ffffff;
        }

        .footer-btn-contact {
            background: #4285f4;
            color: #ffffff;
        }

        .footer-btn-help {
            background: #34a853;
            color: #ffffff;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            nav {
                padding: 1.2rem 3%;
            }

            .hero h1 {
                font-size: 3.5rem;
            }

            .service-card {
                padding: 1.2rem;
            }
        }

        @media (max-width: 1024px) {
            nav {
                padding: 1rem 3%;
            }

            .logo {
                padding-left: 1.5rem;
            }

            .services-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }

            .footer-main {
                grid-template-columns: repeat(2, 1fr);
                gap: 2rem;
            }

            .hero h1 {
                font-size: 3rem;
            }

            .hero p {
                font-size: 1.2rem;
            }

            .modal-content,
            .important-modal-content,
            .safety-modal-content {
                max-width: 90%;
            }

            .inline-register-content {
                padding: 0.8rem 2rem;
            }
        }

        @media (max-width: 768px) {
            nav {
                padding: 1rem 2%;
            }

            .logo {
                padding-left: 1rem;
            }

            .logo-icon-wrapper {
                width: 40px;
                height: 40px;
            }

            .logo-icon-wrapper::before {
                width: 40px;
                height: 40px;
                border-width: 1.5px;
                animation: rippleMobile 3s infinite;
            }

            .logo-img {
                border-width: 1.5px;
            }

            .logo-text {
                font-size: 1.2rem;
            }

            .nav-links {
                display: none;
            }

            .nav-burger {
                display: flex;
            }

            .hero {
                padding: 1rem;
                min-height: 90vh;
            }

            .hero-content {
                max-width: 100%;
            }

            .hero h1 {
                font-size: 2rem;
                line-height: 1.3;
            }

            .hero p {
                font-size: 1rem;
                margin-bottom: 2rem;
            }

            .cta-buttons {
                flex-direction: column;
                gap: 1rem;
                width: 100%;
            }

            .btn {
                width: 100%;
                padding: 0.9rem 2rem;
                font-size: 1rem;
            }

            .section-title {
                font-size: 1.8rem;
            }

            .section-subtitle {
                font-size: 0.95rem;
            }

            .services {
                padding: 4rem 2%;
            }

            .services-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .service-card {
                padding: 1.5rem;
            }

            .service-icon {
                font-size: 2.5rem;
                margin-bottom: 1rem;
            }

            .service-card h3 {
                font-size: 1.1rem;
            }

            .service-card p {
                font-size: 0.9rem;
            }

            .stats {
                padding: 4rem 2%;
            }

            .stats p {
                font-size: 1rem;
                padding: 0 1rem;
            }

            footer {
                padding: 3rem 2% 1.5rem;
            }

            .footer-main {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .footer-column h3 {
                font-size: 1.1rem;
            }

            .footer-column p,
            .footer-column ul li a,
            .footer-column .contact-item {
                font-size: 0.85rem;
            }

            .footer-bottom {
                flex-direction: column;
                gap: 1.5rem;
                align-items: center;
                text-align: center;
            }

            .footer-copyright {
                font-size: 0.8rem;
            }

            .floating-buttons-container {
                bottom: 20px;
                right: 20px;
            }

            .floating-buttons {
                bottom: 0;
                right: 0;
                margin-right: 15px;
            }

            .burger-menu-btn {
                width: 50px;
                height: 50px;
                margin-right: 15px;
                margin-bottom: 15px;
            }

            .burger-menu-btn span {
                width: 20px;
            }

            .floating-btn {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }


            /* Modal Responsive */
            .emergency-contacts-grid,
            .important-contacts-grid,
            .safety-tips-grid {
                grid-template-columns: 1fr;
            }

            .emergency-modal,
            .important-contacts-modal,
            .fire-safety-modal {
                padding: 1rem;
                align-items: flex-start;
                padding-top: 1.5rem;
            }

            .modal-content,
            .important-modal-content,
            .safety-modal-content {
                padding: 1.5rem;
                max-width: 95vw;
                max-height: calc(100vh - 3rem);
                overflow-y: auto;
            }

            .inline-register-panel {
                padding: 3rem 1rem 1rem;
                overflow-y: auto;
                align-items: flex-start;
                width: 100%;
                left: 0;
                right: 0;
            }

            .inline-register-content {
                padding: 0.8rem 1.5rem;
                max-height: calc(100vh - 1rem);
                overflow-y: auto;
                border-radius: 18px;
                gap: 1.25rem;
            }

            body.register-active .hero {
                display: block;
                min-height: 100vh;
                padding-top: 1rem;
            }

            body.register-active .hero-content,
            body.register-active .inline-login-panel {
                display: none;
            }

            .inline-login-form {
                gap: 1rem;
            }

            .inline-login-form-group label {
                left: 0.95rem;
                font-size: 0.9rem;
            }

            .inline-login-form input[type="text"],
            .inline-login-form input[type="password"],
            .inline-login-form input[type="email"],
            .inline-login-form input[type="number"],
            .inline-login-form textarea {
                font-size: 0.95rem;
                padding: 0.85rem 1rem;
            }

            .inline-login-form input:focus + label,
            .inline-login-form input.has-value + label,
            .inline-login-form textarea:focus + label,
            .inline-login-form textarea.has-value + label {
                font-size: 0.7rem;
            }

            .inline-register-header {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 1rem;
            }

            .register-header-left,
            .register-header-right {
                justify-content: center;
            }

            .register-header-right {
                margin-top: 0.25rem;
            }

            .inline-register-close {
                display: none;
            }

            .inline-register-title {
                font-size: 1.6rem;
                line-height: 1.35;
                letter-spacing: 0.02em;
            }

            .register-header-center {
                margin-top: 0.3rem;
            }

            .register-progress {
                width: 100%;
                justify-content: center;
                gap: 0.5rem;
                flex-wrap: wrap;
                margin-top: 0.15rem;
            }

            .progress-step-number {
                width: 32px;
                height: 32px;
                font-size: 0.85rem;
            }

            .progress-connector {
                width: 28px;
                margin-top: -12px;
            }

            .email-verification-group {
                flex-direction: row;
                gap: 0.5rem;
                align-items: stretch;
            }

            .send-verification-btn {
                width: auto;
                min-width: 130px;
                justify-content: center;
                font-size: 0.85rem;
                padding: 0.75rem 0.9rem;
            }

            .send-verification-btn .send-verification-label {
                display: none;
            }

            .inline-login-submit,
            .inline-register-actions button {
                width: 100%;
            }

            .inline-register-actions {
                display: inline-flex;
                align-items: baseline;
                justify-content: center;
                gap: 0.3rem;
            }

            .inline-register-actions button {
                width: auto;
                padding: 0;
                margin: 0;
            }

            .location-map-container {
                min-height: 260px;
            }

            .device-registration-actions {
                flex-direction: column;
                gap: 0.75rem;
            }

            .device-registration-actions .inline-login-submit,
            .device-registration-actions .back-btn {
                width: 100%;
            }

            .modal-header,
            .important-modal-header,
            .safety-modal-header {
                margin-bottom: 1rem;
            }

            .modal-title,
            .important-modal-title,
            .safety-modal-title {
                font-size: 1.5rem;
            }

            .modal-instruction,
            .important-modal-subtitle,
            .safety-modal-subtitle {
                font-size: 0.85rem;
            }

            .contact-card,
            .important-contact-card,
            .safety-tip-card {
                padding: 1rem;
            }

            .contact-card-title,
            .important-contact-title,
            .safety-tip-title {
                font-size: 1rem;
            }

            .contact-card-description,
            .important-contact-description,
            .safety-tip-description {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            nav {
                padding: 0.8rem 1%;
            }

            .logo {
                padding-left: 0.5rem;
                gap: 0.7rem;
            }

            .logo-icon-wrapper {
                width: 35px;
                height: 35px;
            }

            .logo-icon-wrapper::before {
                width: 35px;
                height: 35px;
                border-width: 1px;
                animation: rippleMobileSmall 4s infinite;
            }

            .logo-text {
                font-size: 1rem;
            }

            .hero h1 {
                font-size: 1.75rem;
            }

            .hero p {
                font-size: 0.9rem;
            }

            .btn {
                padding: 0.8rem 1.5rem;
                font-size: 0.9rem;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .services-grid {
                gap: 1rem;
            }

            .service-card {
                padding: 1.2rem;
            }

            .modal-content,
            .important-modal-content,
            .safety-modal-content {
                padding: 1.2rem;
                max-width: 98vw;
            }

            .inline-register-panel {
                padding: 3rem 0 1rem;
                align-items: stretch;
                width: 100%;
            }

            .inline-register-content {
                border-radius: 0;
                min-height: 100vh;
                padding: 0.85rem 1rem 1rem;
                gap: 1rem;
            }

            .inline-register-header {
                gap: 0.65rem;
            }

            .inline-register-title {
                font-size: 1.5rem;
                line-height: 1.35;
                letter-spacing: 0.02em;
            }

            .register-progress {
                flex-wrap: wrap;
                row-gap: 0.45rem;
                gap: 0.45rem;
                margin-top: 0.2rem;
            }

            .progress-step-number {
                width: 26px;
                height: 26px;
                font-size: 0.75rem;
            }

            .progress-connector {
                width: 18px;
                margin-top: -8px;
            }

            .location-map-container {
                min-height: 220px;
            }

            .inline-register-close {
                display: none;
            }

            .inline-register-actions {
                gap: 0.25rem;
            }

            .email-verification-group {
                gap: 0.35rem;
            }

            .send-verification-btn {
                min-width: 48px;
                width: 48px;
                height: 46px;
                padding: 0.65rem;
            }

            .send-verification-btn svg {
                width: 18px;
                height: 18px;
            }

            .inline-login-form input[type="text"],
            .inline-login-form input[type="password"],
            .inline-login-form input[type="email"],
            .inline-login-form input[type="number"],
            .inline-login-form textarea {
                font-size: 0.9rem;
                padding: 0.8rem 0.95rem;
            }

            .inline-login-submit {
                font-size: 0.95rem;
                padding: 0.9rem 1.2rem;
            }

            .floating-buttons-container {
                bottom: 10px;
                right: 10px;
            }

            .floating-buttons {
                margin-right: 12px;
            }

            .burger-menu-btn,
            .floating-btn {
                width: 45px;
                height: 45px;
            }

            .burger-menu-btn {
                margin-right: 12px;
                margin-bottom: 12px;
            }

            .floating-btn::after {
                width: 20px;
                height: 20px;
            }
        }

        /* Glass morphism effect */
        .glass {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <!-- Background Video -->
    <div class="bg-video-container" aria-hidden="true">
        <video autoplay muted loop playsinline preload="auto">
            <source src="images/firebg2.mp4" type="video/mp4">
        </video>
    </div>
    <div class="bg-overlay" aria-hidden="true"></div>

    <!-- Animated Background Particles -->
    <div class="particles" id="particles"></div>

    <!-- Navigation -->
    <nav>
        <div class="logo">
            <div class="logo-icon-wrapper">
                <img src="images/logo1.png" alt="FireGuard Logo" class="logo-img">
            </div>
            <span class="logo-text">FIREGUARD</span>
        </div>
        <ul class="nav-links">
            <li><a href="#home">Home</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
        <button class="nav-burger" onclick="toggleNavMenu()" aria-label="Toggle Navigation Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>

    <!-- Mobile Navigation Overlay -->
    <div class="mobile-nav-overlay" id="mobileNavOverlay" onclick="toggleNavMenu()"></div>

    <!-- Mobile Navigation Menu -->
    <div class="mobile-nav" id="mobileNav">
        <ul class="mobile-nav-links">
            <li><a href="#home">Home</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
    </div>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <h1>MODERN FIRE RESCUE</h1>
            <p>24/7 Emergency Response | Advanced Technology | Professional Team</p>
            <div class="cta-buttons">
                <a href="#services" class="btn btn-primary">Our Services</a>
                <a href="#login" class="btn btn-secondary" id="loginBtn">Login</a>
            </div>
        </div>
        <!-- Sliding Inline Login Panel (enters from right) -->
        <div class="inline-login-panel" id="inlineLoginPanel" aria-hidden="true">
            <div class="inline-login-header">
                <h2 class="inline-login-title">Welcome Back</h2>
                <button class="inline-login-close" id="inlineLoginClose" aria-label="Close Login">✕</button>
            </div>
            <form class="inline-login-form" id="inlineLoginForm">
                <div class="inline-login-form-group">
                    <input type="text" id="inlineLoginUsername" name="username" class="auth-input" placeholder=" " required>
                    <label for="inlineLoginUsername">Username</label>
                </div>
                <div class="inline-login-form-group">
                    <input type="password" id="inlineLoginPassword" name="password" class="auth-input" placeholder=" " required>
                    <label for="inlineLoginPassword">Password</label>
                </div>
                <button type="submit" class="inline-login-submit">Log In</button>
                <h6 class="inline-login-register">
                    Don't have an account? <a href="#" id="openRegisterLink">Register</a>
                </h6>
            </form>
        </div>
        <div class="inline-register-panel" id="inlineRegisterPanel" aria-hidden="true">
            <div class="inline-register-content">
                <div class="inline-register-header">
                    <div class="register-header-left">
                        <h2 class="inline-register-title">Create Your Account</h2>
                    </div>
                    <div class="register-header-center">
                        <!-- Progress Indicator -->
                        <div class="register-progress">
                            <div class="progress-step active">
                                <div class="progress-step-number">1</div>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step">
                                <div class="progress-step-number">2</div>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step">
                                <div class="progress-step-number">3</div>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step">
                                <div class="progress-step-number">4</div>
                            </div>
                        </div>
                    </div>
                    <div class="register-header-right">
                        <button class="inline-register-close" id="inlineRegisterClose" aria-label="Close Register">✕</button>
                    </div>
                </div>

                <form class="inline-login-form" id="inlineRegisterForm">
                    <!-- Step 1: Personal Information -->
                    <div class="register-step active" id="registerStep1">
                        <div class="inline-login-form-group">
                            <input type="text" id="registerFullName" name="fullName" class="auth-input" placeholder=" " required>
                            <label for="registerFullName">Full Name</label>
                        </div>
                        <div class="inline-login-form-group birthdate-input-group">
                            <input type="text" id="registerBirthdate" name="birthdate" class="auth-input calendar-input-trigger" placeholder=" " required>
                            <label for="registerBirthdate">Birthdate</label>
                            <button type="button" class="calendar-icon-btn" id="birthdateCalendarBtn" aria-label="Select Date">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </button>
                        </div>
                        <div class="email-verification-group">
                            <div class="inline-login-form-group email-input-group">
                                <input type="email" id="registerEmail" name="registerEmail" class="auth-input" placeholder=" " required>
                                <label for="registerEmail">Email Address</label>
                            </div>
                            <button type="button" class="send-verification-btn" id="sendVerificationBtn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                                <span class="send-verification-label">Send Verification</span>
                            </button>
                        </div>
                        <div class="inline-login-form-group">
                            <input type="number" id="registerContact" name="contactNumber" class="auth-input" placeholder=" " required>
                            <label for="registerContact">Contact Number</label>
                        </div>
                        <button type="button" class="inline-login-submit" id="nextToLocationBtn">
                            Next: Location
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 0.5rem; display: inline-block;">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Step 2: Location -->
                    <div class="register-step location-step" id="registerStep2">
                        <div class="location-map-container">
                            <div id="locationMap"></div>
                            <button type="button" class="current-location-btn" id="currentLocationBtn" title="Get Current Location">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M12 2v4m0 12v4M2 12h4m12 0h4"></path>
                                </svg>
                                Current Location
                            </button>
                        </div>
                        <div class="location-form-container">
                            <div class="inline-login-form-group validated-field">
                                <textarea id="registerFullAddress" name="fullAddress" class="auth-input address-textarea" placeholder=" " readonly required>Bago City College, Rafael M. Salas Drive, Balingasag, Sampinit, Bago, Negros Occidental, Negros Island Region, 6101, Philippines</textarea>
                                <label for="registerFullAddress">Full Address *</label>
                                <div class="field-checkmark">✓</div>
                            </div>
                            <div class="inline-login-form-group validated-field">
                                <input type="text" id="registerBarangay" name="barangay" class="auth-input" placeholder=" " value="Sampinit" required>
                                <label for="registerBarangay">Barangay *</label>
                                <div class="field-checkmark">✓</div>
                            </div>
                            <div class="inline-login-form-group validated-field">
                                <input type="text" id="registerBuildingName" name="buildingName" class="auth-input" placeholder=" " value="Primary Residence" required>
                                <label for="registerBuildingName">Building Name *</label>
                                <div class="field-checkmark">✓</div>
                            </div>
                            <div class="inline-login-form-group validated-field">
                                <input type="text" id="registerBuildingType" name="buildingType" class="auth-input" placeholder=" " value="Residential" required>
                                <label for="registerBuildingType">Building Type *</label>
                                <div class="field-checkmark">✓</div>
                            </div>
                            <button type="button" class="inline-login-submit" id="nextToAccountBtn">
                                Next: Account Setup
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 0.5rem; display: inline-block;">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </button>
                            <button type="button" class="inline-login-submit btn-secondary" id="backToPersonalBtn" style="margin-top: 0.5rem; background: transparent; border: 1px solid var(--primary-color); color: var(--primary-color);">
                                ← Back
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Device Registration -->
                    <div class="register-step" id="registerStep3">
                        <div class="device-registration-banner">
                            Please register your device. Enter a valid device number and serial number.
                        </div>
                        <div class="inline-login-form-group">
                            <input type="text" id="registerDeviceNumber" name="deviceNumber" class="auth-input" placeholder=" " required>
                            <label for="registerDeviceNumber">Device Number *</label>
                        </div>
                        <div class="inline-login-form-group">
                            <input type="text" id="registerSerialNumber" name="serialNumber" class="auth-input" placeholder=" " required>
                            <label for="registerSerialNumber">Serial Number *</label>
                        </div>
                        <div class="device-registration-actions">
                            <button type="button" class="inline-login-submit back-btn" id="backToLocationBtn">
                                Back
                            </button>
                            <button type="button" class="inline-login-submit" id="nextToCredentialsBtn">
                                Next: Create Credentials
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 0.5rem; display: inline-block;">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="inline-register-actions">
                        Already have an account?
                        <button type="button" id="registerToLogin">Log In</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <h2 class="section-title">Our Services</h2>
        <p class="section-subtitle">FireGuard represents a sophisticated Internet of Things (IoT) based fire detection and emergency response platform engineered to revolutionize fire safety management. Our system integrates cutting-edge sensor technology with real-time monitoring capabilities to deliver unparalleled protection for communities and organizations.</p>
        <div class="services-grid">
            <div class="service-card">
                <span class="service-icon">🔥</span>
                <h3>Real-Time Detection</h3>
                <p>Advanced sensor networks provide instantaneous fire detection with precision accuracy.</p>
            </div>
            <div class="service-card">
                <span class="service-icon">📍</span>
                <h3>Geographic Intelligence</h3>
                <p>GPS-enabled location tracking ensures rapid emergency response coordination.</p>
            </div>
            <div class="service-card">
                <span class="service-icon">📱</span>
                <h3>Instant Alerts</h3>
                <p>Multi-channel notification system reaches emergency responders and stakeholders immediately.</p>
            </div>
            <div class="service-card">
                <span class="service-icon">📊</span>
                <h3>Analytics Dashboard</h3>
                <p>Comprehensive data visualization for informed decision-making and system optimization.</p>
            </div>
        </div>
    </section>

    <!-- Our Commitment Section -->
    <section class="stats" id="about">
        <div style="max-width: 800px; margin: 0 auto; text-align: center;">
            <h2 class="section-title">Our Commitment</h2>
            <p style="color: var(--gray-text); font-size: 1.2rem; line-height: 1.8; margin-top: 2rem;">
                FireGuard is dedicated to enhancing public safety through innovative technology solutions. We collaborate with fire departments, emergency services, and community organizations to create resilient, well-prepared environments that prioritize life safety and property protection.
            </p>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="footer-main">
            <div class="footer-column">
                <h3>FireGuard</h3>
                <p>Dedicated to protecting lives and property through prevention, preparedness, and emergency response.</p>
                <div class="social-icons">
                    <a href="#" class="social-icon" title="Facebook">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                    <a href="#" class="social-icon" title="Twitter">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                        </svg>
                    </a>
                    <a href="#" class="social-icon" title="Instagram">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        </svg>
                    </a>
                    <a href="#" class="social-icon" title="YouTube">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <rect x="2" y="6" width="20" height="12" rx="2" ry="2" fill="#ffffff"/>
                            <path d="M10 8v8l6-4z" fill="#000000"/>
                        </svg>
                    </a>
                </div>
            </div>
            <div class="footer-column">
                <h3>Services</h3>
                <ul>
                    <li><a href="#services">Fire Suppression</a></li>
                    <li><a href="#services">Emergency Medical</a></li>
                    <li><a href="#services">Rescue Operations</a></li>
                    <li><a href="#services">Fire Safety Inspections</a></li>
                    <li><a href="#services">Community Education</a></li>
                    <li><a href="#services">Disaster Preparedness</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Emergency Info</h3>
                <ul>
                    <li><a href="#announcements">Announcements</a></li>
                    <li><a href="#safety-tips">Safety Tips</a></li>
                    <li><a href="#evacuation">Evacuation Plans</a></li>
                    <li><a href="#weather">Weather Alerts</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Contact Info</h3>
                <div class="contact-item">PLDT Landline: <a href="tel:+3434458332">(034) 445-8332</a></div>
                <div class="contact-item">Contact: <a href="tel:+639394248377">0939 424 8377</a></div>
                <div class="contact-item">Email: <a href="mailto:bagocityfirestation@gmail.com">bagocityfirestation@gmail.com</a></div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-copyright">
                © 2025 FireGuard. All Rights Reserved. | Part of the Bureau of Fire Protection
            </div>
        </div>
    </footer>

    <!-- Floating Action Buttons -->
    <div class="floating-buttons-container">
        <button class="burger-menu-btn" onclick="toggleFloatingButtons()" title="Menu" aria-label="Toggle Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="floating-buttons hidden" id="floatingButtons">
            <button class="floating-btn floating-btn-phone" onclick="openEmergencyModal()" title="Emergency Contacts" aria-label="Emergency Contacts"></button>
            <button class="floating-btn floating-btn-contact" onclick="openImportantContactsModal()" title="Important Contacts" aria-label="Important Contacts"></button>
            <button class="floating-btn floating-btn-help" onclick="openFireSafetyModal()" title="Fire Safety Tips" aria-label="Fire Safety Tips"></button>
        </div>
    </div>

    <!-- Important Contacts Modal -->
    <div class="important-contacts-modal" id="importantContactsModal">
        <div class="important-modal-content">
            <div class="important-modal-header">
                <div>
                    <h2 class="important-modal-title">Important Contacts</h2>
                    <p class="important-modal-subtitle">Important contact numbers for various city services and departments.</p>
                </div>
                <button class="modal-close" onclick="closeImportantContactsModal()" aria-label="Close">×</button>
            </div>
            <div class="important-contacts-grid">
                <div class="important-contact-card">
                    <h3 class="important-contact-title">Bago City Hospital</h3>
                    <p class="important-contact-address">Rafael Salas Drive, Barangay Balingasag, Bago City</p>
                    <div class="important-contact-number">
                        <span class="important-contact-icon">📱</span>
                        <a href="tel:09452532198">0945-253-2198</a>
                    </div>
                    <div class="important-contact-number">
                        <span class="important-contact-icon">☎️</span>
                        <a href="tel:0344610552">(034) 461-0552</a> or <a href="tel:0344351690">(034) 435-1690</a>
                    </div>
                </div>
                <div class="important-contact-card">
                    <h3 class="important-contact-title">City Health Office</h3>
                    <p class="important-contact-description">For public health concerns and non-emergency medical inquiries.</p>
                    <div class="important-contact-number">
                        <span class="important-contact-icon">👤</span>
                        <a href="tel:09676325766">0967-632-5766</a>
                    </div>
                    <div class="important-contact-number">
                        <span class="important-contact-icon">☎️</span>
                        <a href="tel:0344610118">(034) 461-0118</a>
                    </div>
                </div>
                <div class="important-contact-card">
                    <h3 class="important-contact-title">DRRM Office / Rescue</h3>
                    <p class="important-contact-description">24/7 disaster response and rescue operations.</p>
                    <div class="important-contact-number">
                        <span class="important-contact-icon">📱</span>
                        <a href="tel:09336936444">0933-693-6444</a> or <a href="tel:09270224884">0927-022-4884</a>
                    </div>
                    <div class="important-contact-number">
                        <span class="important-contact-icon">☎️</span>
                        <a href="tel:0344730043">(034) 473-0043</a>
                    </div>
                </div>
                <div class="important-contact-card">
                    <h3 class="important-contact-title">Philippine Red Cross</h3>
                    <p class="important-contact-description">Emergency response and humanitarian services.</p>
                    <div class="important-contact-number">
                        <span class="important-contact-icon">✚</span>
                        <span style="font-weight: 500;">+</span> <a href="tel:0344348541">(034) 434-8541</a>
                    </div>
                    <div class="important-contact-number">
                        <span class="important-contact-icon">📱</span>
                        <a href="tel:09951281588">0995-128-1588</a> or <a href="tel:09202163069">0920-216-3069</a>
                    </div>
                </div>
                <div class="important-contact-card">
                    <h3 class="important-contact-title">Ma-ao Volunteers Rescue</h3>
                    <p class="important-contact-description">Volunteer rescue unit for emergency assistance.</p>
                    <div class="important-contact-number">
                        <span class="important-contact-icon">📱</span>
                        <a href="tel:09665796001">0966-579-6001</a>
                    </div>
                </div>
                <div class="important-contact-card">
                    <h3 class="important-contact-title">Nationwide Emergency</h3>
                    <p class="important-contact-description">Police, Fire, Medical emergency hotline.</p>
                    <div class="important-contact-number">
                        <span class="important-contact-icon">☎️</span>
                        <a href="tel:911">911</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fire Safety Tips Modal -->
    <div class="fire-safety-modal" id="fireSafetyModal">
        <div class="safety-modal-content">
            <div class="safety-modal-header">
                <div>
                    <h2 class="safety-modal-title">Fire Safety Tips</h2>
                    <p class="safety-modal-subtitle">Essential fire safety tips to protect your home and family.</p>
                </div>
                <button class="modal-close" onclick="closeFireSafetyModal()" aria-label="Close">×</button>
            </div>
            <div class="safety-tips-grid">
                <div class="safety-tip-card">
                    <h3 class="safety-tip-title">Install Smoke Alarms</h3>
                    <p class="safety-tip-description">Place smoke alarms on every level of your home, inside bedrooms and outside sleeping areas. Test them monthly and replace batteries at least once a year.</p>
                </div>
                <div class="safety-tip-card">
                    <h3 class="safety-tip-title">Create an Escape Plan</h3>
                    <p class="safety-tip-description">Develop a fire escape plan with two ways out of every room. Practice it with all family members at least twice a year.</p>
                </div>
                <div class="safety-tip-card">
                    <h3 class="safety-tip-title">Kitchen Safety</h3>
                    <p class="safety-tip-description">Never leave cooking unattended. Keep flammable items away from the stove. Turn pot handles inward to prevent spills.</p>
                </div>
                <div class="safety-tip-card">
                    <h3 class="safety-tip-title">Electrical Safety</h3>
                    <p class="safety-tip-description">Don't overload outlets. Replace damaged cords. Use bulbs with the correct wattage for fixtures.</p>
                </div>
                <div class="safety-tip-card">
                    <h3 class="safety-tip-title">Heating Safety</h3>
                    <p class="safety-tip-description">Keep anything flammable at least 3 feet from heating equipment. Turn portable heaters off when leaving the room or going to bed.</p>
                </div>
                <div class="safety-tip-card">
                    <h3 class="safety-tip-title">Stop, Drop, and Roll</h3>
                    <p class="safety-tip-description">If your clothes catch fire, stop immediately, drop to the ground, and roll to extinguish the flames. Cover your face with your hands.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Emergency Contacts Modal -->
    <div class="emergency-modal" id="emergencyModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Emergency Contacts</h2>
                <button class="modal-close" onclick="closeEmergencyModal()" aria-label="Close">×</button>
            </div>
            <p class="modal-instruction">In case of emergency, please contact the following numbers immediately. For life-threatening situations, call the emergency hotline first.</p>
            <div class="emergency-contacts-grid">
                <div class="contact-card">
                    <h3 class="contact-card-title">Fire Emergency</h3>
                    <p class="contact-card-description">Immediate response for fire incidents, rescue operations, and hazardous material situations.</p>
                    <div class="contact-info">
                        <span class="contact-icon">📞</span>
                        <a href="tel:911" style="color: #ffffff; text-decoration: none;">911</a>
                    </div>
                </div>
                <div class="contact-card">
                    <h3 class="contact-card-title">Police Department</h3>
                    <p class="contact-card-description">For criminal activities, traffic accidents, and public safety concerns.</p>
                    <div class="contact-info">
                        <span class="contact-icon">📞</span>
                        <a href="tel:09298215539" style="color: #ffffff; text-decoration: none;">09298215539</a>
                    </div>
                </div>
                <div class="contact-card">
                    <h3 class="contact-card-title">Medical Emergency</h3>
                    <p class="contact-card-description">Ambulance service and medical emergencies.</p>
                    <div class="contact-info">
                        <span class="contact-icon">🚑</span>
                        <a href="tel:09452532198" style="color: #ffffff; text-decoration: none;">0945-253-2198</a>
                    </div>
                </div>
                <div class="contact-card">
                    <h3 class="contact-card-title">Disaster Management</h3>
                    <p class="contact-card-description">For typhoons, floods, earthquakes, and other natural disasters.</p>
                    <div class="contact-info">
                        <span class="contact-icon">📞</span>
                        <span style="color: #ffffff;">
                            <a href="tel:09336936444" style="color: #ffffff; text-decoration: none;">0933-693-6444</a> or <a href="tel:09270224884" style="color: #ffffff; text-decoration: none;">0927-022-4884</a>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet Map Library -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
        // Create animated particles
        const particlesContainer = document.getElementById('particles');
        const particleCount = 50;

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 15 + 's';
            particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
            particlesContainer.appendChild(particle);
        }

        // Smooth scrolling and active nav state
        const navLinks = document.querySelectorAll('.nav-links a');
        
        // Function to set active nav link
        function setActiveNavLink(href) {
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === href) {
                    link.classList.add('active');
                }
            });
        }

        // Set active nav link on click
        let isClicking = false;
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                const target = document.querySelector(href);
                if (target) {
                    // Set active state for nav links
                    if (this.closest('.nav-links')) {
                        isClicking = true;
                        setActiveNavLink(href);
                        
                        // Reset clicking flag after scroll completes
                        setTimeout(() => {
                            isClicking = false;
                        }, 1000);
                    }
                    
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Set active nav link based on scroll position
        function updateActiveNavOnScroll() {
            // Don't update if user just clicked a nav link
            if (isClicking) return;
            
            const sections = document.querySelectorAll('section[id], footer[id]');
            const scrollPosition = window.scrollY + 100;
            const windowHeight = window.innerHeight;
            const documentHeight = document.documentElement.scrollHeight;

            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.offsetHeight;
                const sectionId = section.getAttribute('id');

                // For footer, also check if we're near the bottom of the page
                if (section.tagName === 'FOOTER') {
                    if (scrollPosition >= sectionTop || (windowHeight + window.scrollY >= documentHeight - 50)) {
                        setActiveNavLink('#' + sectionId);
                    }
                } else if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                    setActiveNavLink('#' + sectionId);
                }
            });
        }

        // Update active nav on scroll
        window.addEventListener('scroll', updateActiveNavOnScroll);
        
        // Set initial active nav link on page load
        window.addEventListener('load', () => {
            updateActiveNavOnScroll();
            // If at top of page, set Home as active
            if (window.scrollY < 100) {
                setActiveNavLink('#home');
            }
        });

        // Inline Login slide interaction
        const loginBtn = document.getElementById('loginBtn');
        const heroSection = document.querySelector('section.hero#home');
        const heroContent = heroSection ? heroSection.querySelector('.hero-content') : null;
        const inlineLoginPanel = document.getElementById('inlineLoginPanel');
        const inlineLoginClose = document.getElementById('inlineLoginClose');
        const inlineLoginForm = document.getElementById('inlineLoginForm');
        const inlineRegisterPanel = document.getElementById('inlineRegisterPanel');
        const inlineRegisterClose = document.getElementById('inlineRegisterClose');
        const inlineRegisterForm = document.getElementById('inlineRegisterForm');
        const registerToLogin = document.getElementById('registerToLogin');
        const openRegisterLink = document.getElementById('openRegisterLink');
        const registerFullNameInput = document.getElementById('registerFullName');

        function updateHeroSlideState() {
            if (!heroContent) return;
            const isActive = (inlineLoginPanel && inlineLoginPanel.classList.contains('active')) ||
                            (inlineRegisterPanel && inlineRegisterPanel.classList.contains('active'));
            heroContent.classList.toggle('slide-left', isActive);
        }

        // Helper function to safely hide a panel (remove focus before setting aria-hidden)
        function safelyHidePanel(panel) {
            if (!panel) return;
            
            // Remove focus from any focused element inside the panel
            const focusedElement = panel.querySelector(':focus');
            if (focusedElement && focusedElement.blur) {
                focusedElement.blur();
            }
            
            // Also blur document.activeElement if it's inside this panel
            if (document.activeElement && panel.contains(document.activeElement)) {
                document.activeElement.blur();
            }
            
            // Set aria-hidden after removing focus
            panel.classList.remove('active');
            panel.setAttribute('aria-hidden', 'true');
        }

        function openInlineLogin() {
            document.body.classList.remove('register-active');
            // Safely hide register panel if it's open
            safelyHidePanel(inlineRegisterPanel);
            if (inlineLoginPanel) {
                inlineLoginPanel.classList.add('active');
                inlineLoginPanel.setAttribute('aria-hidden', 'false');
            }
            // Ensure hero is in view below navbar
            const nav = document.querySelector('nav');
            const navHeight = nav ? nav.offsetHeight : 0;
            const top = heroSection ? heroSection.offsetTop - navHeight - 12 : 0;
            window.scrollTo({ top, behavior: 'smooth' });
            updateHeroSlideState();

            requestAnimationFrame(() => {
                authInputs.forEach(input => {
                    const group = input.closest('.inline-login-form-group');
                    if (group && (group.classList.contains('label-raised') || input === document.activeElement)) {
                        updateFloatingLabelState(input);
                    }
                });
            });
        }

        function openInlineRegister() {
            document.body.classList.add('register-active');
            // Safely hide login panel if it's open
            safelyHidePanel(inlineLoginPanel);
            if (inlineRegisterPanel) {
                inlineRegisterPanel.classList.add('active');
                inlineRegisterPanel.setAttribute('aria-hidden', 'false');
            }
            // Reset to step 1 when opening
            setTimeout(() => {
                const step1 = document.getElementById('registerStep1');
                const step2 = document.getElementById('registerStep2');
                const step3 = document.getElementById('registerStep3');
                const progressSteps = document.querySelectorAll('.progress-step');
                if (step1 && step2 && step3) {
                    step1.classList.add('active');
                    step2.classList.remove('active');
                    step3.classList.remove('active');
                    // Reset progress indicator
                    progressSteps.forEach((progressStep, index) => {
                        if (index === 0) {
                            progressStep.classList.add('active');
                            progressStep.classList.remove('completed');
                        } else {
                            progressStep.classList.remove('active', 'completed');
                        }
                    });
                    // Reset title to default
                    const registerTitle = document.querySelector('.inline-register-title');
                    if (registerTitle) {
                        registerTitle.textContent = 'Create Your Account';
                    }
                }
            }, 100);
            // Ensure hero visible
            const nav = document.querySelector('nav');
            const navHeight = nav ? nav.offsetHeight : 0;
            const top = heroSection ? heroSection.offsetTop - navHeight - 12 : 0;
            window.scrollTo({ top, behavior: 'smooth' });
            updateHeroSlideState();

            requestAnimationFrame(() => {
                authInputs.forEach(input => {
                    const group = input.closest('.inline-login-form-group');
                    if (group && (group.classList.contains('label-raised') || input === document.activeElement)) {
                        updateFloatingLabelState(input);
                    }
                });
                const registerFullNameInput = document.getElementById('registerFullName');
                if (registerFullNameInput) {
                    registerFullNameInput.focus();
                }
                // Initialize calendar when registration panel opens
                setTimeout(() => {
                    initBirthdateCalendar();
                }, 200);
            });
        }

        function closeInlineLogin() {
            safelyHidePanel(inlineLoginPanel);
            updateHeroSlideState();
        }

        function closeInlineRegister() {
            document.body.classList.remove('register-active');
            safelyHidePanel(inlineRegisterPanel);
            updateHeroSlideState();
        }

        if (loginBtn) {
            loginBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openInlineLogin();
            });
        }

        if (inlineLoginClose) {
            inlineLoginClose.addEventListener('click', function() {
                closeInlineLogin();
            });
        }

        if (inlineRegisterClose) {
            inlineRegisterClose.addEventListener('click', function() {
                closeInlineRegister();
            });
        }

        if (inlineLoginForm) {
            inlineLoginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                // Placeholder: handle login
                closeInlineLogin();
            });
        }

        if (inlineRegisterForm) {
            inlineRegisterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                // Placeholder: handle registration
                closeInlineRegister();
            });
        }

        // Registration Step Navigation
        const nextToLocationBtn = document.getElementById('nextToLocationBtn');
        const registerStep1 = document.getElementById('registerStep1');
        const registerStep2 = document.getElementById('registerStep2');
        const registerStep3 = document.getElementById('registerStep3');
        const progressSteps = document.querySelectorAll('.progress-step');
        let currentStep = 1;

        function updateProgressIndicator(step) {
            progressSteps.forEach((progressStep, index) => {
                if (index < step - 1) {
                    progressStep.classList.add('completed');
                    progressStep.classList.remove('active');
                } else if (index === step - 1) {
                    progressStep.classList.add('active');
                    progressStep.classList.remove('completed');
                } else {
                    progressStep.classList.remove('active', 'completed');
                }
            });
        }

        function showStep(stepNumber) {
            // Hide all steps
            registerStep1.classList.remove('active');
            registerStep2.classList.remove('active');
            registerStep3.classList.remove('active');

            // Update title based on step
            const registerTitle = document.querySelector('.inline-register-title');
            if (registerTitle) {
                if (stepNumber === 3) {
                    registerTitle.textContent = 'Device Registration';
                } else {
                    registerTitle.textContent = 'Create Your Account';
                }
            }

            // Show current step
            if (stepNumber === 1) {
                registerStep1.classList.add('active');
            } else if (stepNumber === 2) {
                registerStep2.classList.add('active');
                // Initialize map when Step 2 is shown
                setTimeout(() => {
                    initLocationMap();
                }, 300);
                // Initialize floating labels for pre-filled fields
                setTimeout(() => {
                    const step2Inputs = registerStep2.querySelectorAll('.auth-input');
                    step2Inputs.forEach(input => {
                        updateFloatingLabelState(input);
                    });
                }, 100);
            } else if (stepNumber === 3) {
                registerStep3.classList.add('active');
            }

            updateProgressIndicator(stepNumber);
            currentStep = stepNumber;
        }

        if (nextToLocationBtn) {
            nextToLocationBtn.addEventListener('click', function(e) {
                e.preventDefault();
                // Validate step 1 fields
                const fullName = document.getElementById('registerFullName');
                const birthdate = document.getElementById('registerBirthdate');
                const email = document.getElementById('registerEmail');
                const contact = document.getElementById('registerContact');

                if (fullName.value && birthdate.value && email.value && contact.value) {
                    showStep(2);
                } else {
                    // Trigger validation
                    inlineRegisterForm.reportValidity();
                }
            });
        }

        // Step 2 Navigation
        const nextToAccountBtn = document.getElementById('nextToAccountBtn');
        const backToPersonalBtn = document.getElementById('backToPersonalBtn');

        if (nextToAccountBtn) {
            nextToAccountBtn.addEventListener('click', function(e) {
                e.preventDefault();
                // Validate step 2 fields
                const fullAddress = document.getElementById('registerFullAddress');
                const barangay = document.getElementById('registerBarangay');
                const buildingName = document.getElementById('registerBuildingName');
                const buildingType = document.getElementById('registerBuildingType');

                if (fullAddress && fullAddress.value && barangay && barangay.value && buildingName && buildingName.value && buildingType && buildingType.value) {
                    showStep(3);
                } else {
                    // Trigger validation
                    inlineRegisterForm.reportValidity();
                }
            });
        }

        if (backToPersonalBtn) {
            backToPersonalBtn.addEventListener('click', function(e) {
                e.preventDefault();
                showStep(1);
            });
        }

        // Step 3 Navigation
        const nextToCredentialsBtn = document.getElementById('nextToCredentialsBtn');
        const backToLocationBtn = document.getElementById('backToLocationBtn');

        if (nextToCredentialsBtn) {
            nextToCredentialsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                // Validate step 3 fields
                const deviceNumber = document.getElementById('registerDeviceNumber');
                const serialNumber = document.getElementById('registerSerialNumber');

                if (deviceNumber.value && serialNumber.value) {
                    // Placeholder: proceed to credentials step (Step 4 if exists, or submit)
                    alert('Device registered successfully! Proceeding to credentials...');
                    // You can add Step 4 here or submit the form
                } else {
                    // Trigger validation
                    inlineRegisterForm.reportValidity();
                }
            });
        }

        if (backToLocationBtn) {
            backToLocationBtn.addEventListener('click', function(e) {
                e.preventDefault();
                showStep(2);
            });
        }

        // Map Initialization for Step 2
        let locationMap = null;
        let locationMarker = null;

        function initLocationMap() {
            const mapContainer = document.getElementById('locationMap');
            if (!mapContainer || locationMap) return;

            // Initialize map centered on Philippines (default location)
            locationMap = L.map('locationMap').setView([14.5995, 120.9842], 13);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(locationMap);

            // Add default marker
            locationMarker = L.marker([14.5995, 120.9842], {
                draggable: true
            }).addTo(locationMap);

            // Update address fields when marker is dragged
            locationMarker.on('dragend', function(e) {
                const position = locationMarker.getLatLng();
                updateAddressFromCoordinates(position.lat, position.lng);
            });

            // Add click event to map to set marker position
            locationMap.on('click', function(e) {
                if (locationMarker) {
                    locationMarker.setLatLng(e.latlng);
                } else {
                    locationMarker = L.marker(e.latlng, {
                        draggable: true
                    }).addTo(locationMap);
                    locationMarker.on('dragend', function(e) {
                        const position = locationMarker.getLatLng();
                        updateAddressFromCoordinates(position.lat, position.lng);
                    });
                }
                updateAddressFromCoordinates(e.latlng.lat, e.latlng.lng);
            });

            // Function to get and set current location
            function setCurrentLocation() {
                if (navigator.geolocation) {
                    const btn = document.getElementById('currentLocationBtn');
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.7';
                        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 2v4m0 12v4M2 12h4m12 0h4"></path></svg> Locating...';
                    }
                    
                    navigator.geolocation.getCurrentPosition(function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        locationMap.setView([lat, lng], 15);
                        if (locationMarker) {
                            locationMarker.setLatLng([lat, lng]);
                        } else {
                            locationMarker = L.marker([lat, lng], {
                                draggable: true
                            }).addTo(locationMap);
                            locationMarker.on('dragend', function(e) {
                                const position = locationMarker.getLatLng();
                                updateAddressFromCoordinates(position.lat, position.lng);
                            });
                        }
                        updateAddressFromCoordinates(lat, lng);
                        
                        if (btn) {
                            btn.disabled = false;
                            btn.style.opacity = '1';
                            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 2v4m0 12v4M2 12h4m12 0h4"></path></svg> Current Location';
                        }
                    }, function(error) {
                        console.log('Geolocation error:', error);
                        alert('Unable to get your location. Please check your browser permissions.');
                        const btn = document.getElementById('currentLocationBtn');
                        if (btn) {
                            btn.disabled = false;
                            btn.style.opacity = '1';
                            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 2v4m0 12v4M2 12h4m12 0h4"></path></svg> Current Location';
                        }
                    });
                } else {
                    alert('Geolocation is not supported by your browser.');
                }
            }

            // Try to get user's current location on initial load
            if (navigator.geolocation) {
                setCurrentLocation();
            }

            // Add event listener for current location button
            const currentLocationBtn = document.getElementById('currentLocationBtn');
            if (currentLocationBtn) {
                currentLocationBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    setCurrentLocation();
                });
            }

            // Update map when address fields change
            const addressInputs = ['registerStreet', 'registerCity', 'registerProvince', 'registerPostalCode'];
            addressInputs.forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input) {
                    input.addEventListener('blur', function() {
                        if (input.value) {
                            geocodeAddress();
                        }
                    });
                }
            });
        }

        function updateAddressFromCoordinates(lat, lng) {
            // Use Nominatim (OpenStreetMap geocoding) to get address
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.address) {
                        const addr = data.address;
                        const streetInput = document.getElementById('registerStreet');
                        const cityInput = document.getElementById('registerCity');
                        const provinceInput = document.getElementById('registerProvince');
                        const postalCodeInput = document.getElementById('registerPostalCode');

                        // Street Address - try multiple fields
                        if (streetInput) {
                            let streetValue = '';
                            if (addr.house_number) {
                                streetValue += addr.house_number + ' ';
                            }
                            if (addr.road) {
                                streetValue += addr.road;
                            } else if (addr.street) {
                                streetValue += addr.street;
                            } else if (addr.neighbourhood) {
                                streetValue += addr.neighbourhood;
                            } else if (addr.suburb) {
                                streetValue += addr.suburb;
                            }
                            if (streetValue.trim()) {
                                streetInput.value = streetValue.trim();
                            }
                        }

                        // City - try multiple fields
                        if (cityInput) {
                            if (addr.city) {
                                cityInput.value = addr.city;
                            } else if (addr.town) {
                                cityInput.value = addr.town;
                            } else if (addr.municipality) {
                                cityInput.value = addr.municipality;
                            } else if (addr.village) {
                                cityInput.value = addr.village;
                            } else if (addr.suburb) {
                                cityInput.value = addr.suburb;
                            } else if (addr.neighbourhood) {
                                cityInput.value = addr.neighbourhood;
                            }
                        }

                        // Province/State - try multiple fields
                        if (provinceInput) {
                            if (addr.state) {
                                provinceInput.value = addr.state;
                            } else if (addr.province) {
                                provinceInput.value = addr.province;
                            } else if (addr.region) {
                                provinceInput.value = addr.region;
                            } else if (addr.state_district) {
                                provinceInput.value = addr.state_district;
                            }
                        }

                        // Postal Code
                        if (postalCodeInput && addr.postcode) {
                            postalCodeInput.value = addr.postcode;
                        }

                        // Trigger input events to ensure validation
                        [streetInput, cityInput, provinceInput, postalCodeInput].forEach(input => {
                            if (input && input.value) {
                                input.dispatchEvent(new Event('input', { bubbles: true }));
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        });
                    }
                })
                .catch(error => {
                    console.log('Geocoding error:', error);
                });
        }

        function geocodeAddress() {
            const street = document.getElementById('registerStreet')?.value || '';
            const city = document.getElementById('registerCity')?.value || '';
            const province = document.getElementById('registerProvince')?.value || '';
            const postalCode = document.getElementById('registerPostalCode')?.value || '';

            if (!street && !city && !province) return;

            const query = `${street}, ${city}, ${province}, ${postalCode}`.trim().replace(/^,\s*|,\s*$/g, '');
            
            if (!query) return;

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);
                        if (locationMap) {
                            locationMap.setView([lat, lng], 15);
                            if (locationMarker) {
                                locationMarker.setLatLng([lat, lng]);
                            } else {
                                locationMarker = L.marker([lat, lng], {
                                    draggable: true
                                }).addTo(locationMap);
                                locationMarker.on('dragend', function(e) {
                                    const position = locationMarker.getLatLng();
                                    updateAddressFromCoordinates(position.lat, position.lng);
                                });
                            }
                        }
                    }
                })
                .catch(error => {
                    console.log('Geocoding error:', error);
                });
        }


        // Calendar Button for Birthdate - Initialize function
        function initBirthdateCalendar() {
            const birthdateCalendarBtn = document.getElementById('birthdateCalendarBtn');
            const registerBirthdateInput = document.getElementById('registerBirthdate');
            
            if (!birthdateCalendarBtn || !registerBirthdateInput) return;
            
            // Remove existing listener if any (by cloning the button)
            const newBtn = birthdateCalendarBtn.cloneNode(true);
            birthdateCalendarBtn.parentNode.replaceChild(newBtn, birthdateCalendarBtn);
            
            // Get the new button reference
            const btn = document.getElementById('birthdateCalendarBtn');
            
            if (!btn) return;
            
            // Create or reuse hidden date input
            let hiddenDateInput = document.getElementById('hiddenBirthdatePicker');
            if (hiddenDateInput && hiddenDateInput.parentNode) {
                hiddenDateInput.parentNode.removeChild(hiddenDateInput);
            }
            
            hiddenDateInput = document.createElement('input');
            hiddenDateInput.id = 'hiddenBirthdatePicker';
            hiddenDateInput.type = 'date';
            
            // Position it absolutely but within viewport
            const rect = registerBirthdateInput.getBoundingClientRect();
            hiddenDateInput.style.position = 'fixed';
            hiddenDateInput.style.opacity = '0';
            hiddenDateInput.style.width = Math.max(rect.width, 200) + 'px';
            hiddenDateInput.style.height = Math.max(rect.height, 40) + 'px';
            hiddenDateInput.style.top = rect.top + 'px';
            hiddenDateInput.style.left = rect.left + 'px';
            hiddenDateInput.style.pointerEvents = 'none';
            hiddenDateInput.style.zIndex = '9999';
            
            document.body.appendChild(hiddenDateInput);

            const showPicker = function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Update position in case the panel moved
                const rect = registerBirthdateInput.getBoundingClientRect();
                hiddenDateInput.style.top = rect.top + 'px';
                hiddenDateInput.style.left = rect.left + 'px';
                hiddenDateInput.style.width = Math.max(rect.width, 200) + 'px';
                hiddenDateInput.style.height = Math.max(rect.height, 40) + 'px';
                
                // Set the current value if it exists (try to parse common date formats)
                if (registerBirthdateInput.value) {
                    const dateValue = registerBirthdateInput.value;
                    // Try to parse YYYY-MM-DD format
                    if (dateValue.match(/^\d{4}-\d{2}-\d{2}$/)) {
                        hiddenDateInput.value = dateValue;
                    } else {
                        // Try to parse other common formats
                        const parsedDate = new Date(dateValue);
                        if (!isNaN(parsedDate.getTime())) {
                            const year = parsedDate.getFullYear();
                            const month = String(parsedDate.getMonth() + 1).padStart(2, '0');
                            const day = String(parsedDate.getDate()).padStart(2, '0');
                            hiddenDateInput.value = `${year}-${month}-${day}`;
                        }
                    }
                }
                
                // Temporarily make input clickable for date picker
                hiddenDateInput.style.pointerEvents = 'auto';
                
                // Use showPicker if available (modern browsers)
                if (hiddenDateInput.showPicker) {
                    try {
                        hiddenDateInput.focus();
                        hiddenDateInput.showPicker();
                    } catch (err) {
                        // Fallback: click the hidden input
                        setTimeout(() => {
                            hiddenDateInput.focus();
                            hiddenDateInput.click();
                        }, 10);
                    }
                } else {
                    // Fallback for older browsers
                    setTimeout(() => {
                        hiddenDateInput.focus();
                        hiddenDateInput.click();
                    }, 10);
                }
            };

            btn.addEventListener('click', showPicker);
            registerBirthdateInput.addEventListener('click', showPicker);
            registerBirthdateInput.addEventListener('touchend', showPicker);

            // Sync the date value back to the text input
            hiddenDateInput.addEventListener('change', function() {
                if (hiddenDateInput.value) {
                    registerBirthdateInput.value = hiddenDateInput.value;
                    // Trigger input event to update floating label
                    const inputEvent = new Event('input', { bubbles: true });
                    registerBirthdateInput.dispatchEvent(inputEvent);
                    // Also trigger change event
                    const changeEvent = new Event('change', { bubbles: true });
                    registerBirthdateInput.dispatchEvent(changeEvent);
                }
                // Reset pointer events after date selection
                setTimeout(() => {
                    hiddenDateInput.style.pointerEvents = 'none';
                }, 100);
            });
        }

        // Send Verification Button
        const sendVerificationBtn = document.getElementById('sendVerificationBtn');
        if (sendVerificationBtn) {
            sendVerificationBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const email = document.getElementById('registerEmail');
                if (email.value && email.validity.valid) {
                    // Placeholder: Send verification email
                    alert('Verification code sent to ' + email.value);
                    const svg = sendVerificationBtn.querySelector('svg');
                    sendVerificationBtn.innerHTML = svg.outerHTML + ' Resend Verification';
                } else {
                    email.focus();
                    email.reportValidity();
                }
            });
        }

        if (openRegisterLink) {
            // Handle both click and touch events for mobile compatibility
            const handleRegisterClick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                openInlineRegister();
                return false;
            };
            
            openRegisterLink.addEventListener('click', handleRegisterClick);
            openRegisterLink.addEventListener('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openInlineRegister();
                return false;
            });
        }

        if (registerToLogin) {
            // Handle both click and touch events for mobile compatibility
            const handleLoginClick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                openInlineLogin();
                return false;
            };
            
            registerToLogin.addEventListener('click', handleLoginClick);
            registerToLogin.addEventListener('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openInlineLogin();
                return false;
            });
        }

        // Floating label effect for auth inputs
        const authInputs = document.querySelectorAll('.auth-input');

        function updateFloatingLabelState(input) {
            const group = input.closest('.inline-login-form-group');
            const label = group ? group.querySelector('label') : null;

            const hasValue = !!input.value;
            input.classList.toggle('has-value', hasValue);

            if (!group || !label) return;

            const shouldRaise = hasValue || document.activeElement === input;
            group.classList.toggle('label-raised', shouldRaise);

            if (shouldRaise) {
                const groupRect = group.getBoundingClientRect();
                const labelRect = label.getBoundingClientRect();
                const inset = 6; // small gap around the label

                const rightGap = Math.max(groupRect.right - labelRect.right - inset, 0);

                group.style.setProperty('--label-gap-right', `${rightGap}px`);
            } else {
                group.style.removeProperty('--label-gap-right');
            }
        }

        authInputs.forEach(input => {
            updateFloatingLabelState(input);

            input.addEventListener('input', function() {
                updateFloatingLabelState(this);
            });

            input.addEventListener('focus', function() {
                const group = this.closest('.inline-login-form-group');
                if (group) {
                    group.classList.add('label-focused');
                }
                updateFloatingLabelState(this);
            });

            input.addEventListener('blur', function() {
                const group = this.closest('.inline-login-form-group');
                if (group) {
                    group.classList.remove('label-focused');
                }
                updateFloatingLabelState(this);
            });
        });

        window.addEventListener('resize', () => {
            authInputs.forEach(input => {
                const group = input.closest('.inline-login-form-group');
                if (group && group.classList.contains('label-raised')) {
                    updateFloatingLabelState(input);
                }
            });
        });

        // Close with Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeInlineLogin();
                closeInlineRegister();
            }
        });

        // Emergency Modal Functions
        function openEmergencyModal() {
            const modal = document.getElementById('emergencyModal');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
            }
        }

        function closeEmergencyModal() {
            const modal = document.getElementById('emergencyModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto'; // Restore scrolling
            }
        }

        // Close modal when clicking outside of it
        document.getElementById('emergencyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEmergencyModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEmergencyModal();
                closeImportantContactsModal();
                closeFireSafetyModal();
            }
        });

        // Important Contacts Modal Functions
        function openImportantContactsModal() {
            const modal = document.getElementById('importantContactsModal');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
            }
        }

        function closeImportantContactsModal() {
            const modal = document.getElementById('importantContactsModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto'; // Restore scrolling
            }
        }

        // Close important contacts modal when clicking outside of it
        document.getElementById('importantContactsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImportantContactsModal();
            }
        });

        // Fire Safety Tips Modal Functions
        function openFireSafetyModal() {
            const modal = document.getElementById('fireSafetyModal');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
            }
        }

        function closeFireSafetyModal() {
            const modal = document.getElementById('fireSafetyModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto'; // Restore scrolling
            }
        }

        // Close fire safety modal when clicking outside of it
        document.getElementById('fireSafetyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeFireSafetyModal();
            }
        });

        // Toggle Floating Buttons Function
        function toggleFloatingButtons() {
            const buttons = document.getElementById('floatingButtons');
            const burgerBtn = document.querySelector('.burger-menu-btn');
            
            if (buttons && burgerBtn) {
                const isHidden = buttons.classList.contains('hidden');
                if (isHidden) {
                    buttons.classList.remove('hidden');
                    burgerBtn.classList.add('active');
                } else {
                    buttons.classList.add('hidden');
                    burgerBtn.classList.remove('active');
                }
            }
        }

        // Toggle Navigation Menu Function
        function toggleNavMenu() {
            const mobileNav = document.getElementById('mobileNav');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            const navBurger = document.querySelector('.nav-burger');
            const nav = document.querySelector('nav');
            
            if (mobileNav && navBurger && nav) {
                // Calculate navbar height and set mobile nav position
                const navHeight = nav.offsetHeight;
                document.documentElement.style.setProperty('--nav-height', navHeight + 'px');
                mobileNav.style.top = navHeight + 'px';
                mobileNav.style.marginTop = '0';
                
                const isActive = mobileNav.classList.contains('active');
                if (isActive) {
                    mobileNav.classList.remove('active');
                    if (mobileNavOverlay) mobileNavOverlay.classList.remove('active');
                    navBurger.classList.remove('active');
                    document.body.style.overflow = 'auto';
                } else {
                    mobileNav.classList.add('active');
                    if (mobileNavOverlay) mobileNavOverlay.classList.add('active');
                    navBurger.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            }
        }

        // Close mobile nav when clicking outside
        document.addEventListener('click', function(e) {
            const mobileNav = document.getElementById('mobileNav');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            const navBurger = document.querySelector('.nav-burger');
            
            if (mobileNav && navBurger && mobileNav.classList.contains('active')) {
                if (!mobileNav.contains(e.target) && !navBurger.contains(e.target)) {
                    mobileNav.classList.remove('active');
                    if (mobileNavOverlay) mobileNavOverlay.classList.remove('active');
                    navBurger.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }
            }
        });

        // Handle mobile nav link clicks
        const mobileNavLinks = document.querySelectorAll('.mobile-nav-links a');
        mobileNavLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                const target = document.querySelector(href);
                
                if (target) {
                    // Set active nav link
                    setActiveNavLink(href);
                    
                    // Close mobile menu
                    const mobileNav = document.getElementById('mobileNav');
                    const mobileNavOverlay = document.getElementById('mobileNavOverlay');
                    const navBurger = document.querySelector('.nav-burger');
                    
                    if (mobileNav && navBurger) {
                        mobileNav.classList.remove('active');
                        if (mobileNavOverlay) mobileNavOverlay.classList.remove('active');
                        navBurger.classList.remove('active');
                        document.body.style.overflow = 'auto';
                    }
                    
                    // Calculate offset for navbar height
                    const nav = document.querySelector('nav');
                    const navHeight = nav ? nav.offsetHeight : 0;
                    const targetPosition = target.offsetTop - navHeight;
                    
                    // Smooth scroll to target
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Auto-open login panel on mobile devices after page load
        window.addEventListener('load', () => {
            if (window.innerWidth <= 768 && inlineLoginPanel) {
                // Small delay to ensure page is fully loaded and functions are available
                setTimeout(() => {
                    openInlineLogin();
                }, 300);
            }
        });
    </script>
</body>
</html>

