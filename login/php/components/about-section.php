</head>
<body>

<!-- About Section -->
<section class="about" id="about" aria-label="About FireGuard">
    <div class="container">
        <div class="about-content">
            <div class="about-card">
                <div class="card-content">
                    <!-- Card Header with Title -->
                    <div class="card-header">
                        <div class="header-content">
                            <span class="about-label">ABOUT FIREGUARD</span>
                            <h2 class="about-title">Advanced Fire Detection and Emergency Response System</h2>
                            <p class="about-subtitle">Empowering communities through intelligent fire safety technology</p>
                        </div>
                        <div class="card-icon">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L13.09 8.26L20 9L13.09 9.74L12 16L10.91 9.74L4 9L10.91 8.26L12 2Z" fill="currentColor"/>
                                <path d="M12 2L13.09 8.26L20 9L13.09 9.74L12 16L10.91 9.74L4 9L10.91 8.26L12 2Z" fill="currentColor" opacity="0.3"/>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="card-body">
                        <p class="card-description">
                            FireGuard represents a sophisticated Internet of Things (IoT) based fire detection and emergency response platform engineered to revolutionize fire safety management. Our system integrates cutting-edge sensor technology with real-time monitoring capabilities to deliver unparalleled protection for communities and organizations.
                        </p>
                        
                        <div class="feature-grid">
                            <div class="feature-item">
                                <div class="feature-icon">🔥</div>
                                <div class="feature-content">
                                    <h4>Real-Time Detection</h4>
                                    <p>Advanced sensor networks provide instantaneous fire detection with precision accuracy</p>
                                </div>
                            </div>
                            
                            <div class="feature-item">
                                <div class="feature-icon">📍</div>
                                <div class="feature-content">
                                    <h4>Geographic Intelligence</h4>
                                    <p>GPS-enabled location tracking ensures rapid emergency response coordination</p>
                                </div>
                            </div>
                            
                            <div class="feature-item">
                                <div class="feature-icon">📱</div>
                                <div class="feature-content">
                                    <h4>Instant Alerts</h4>
                                    <p>Multi-channel notification system reaches emergency responders and stakeholders immediately</p>
                                </div>
                            </div>
                            
                            <div class="feature-item">
                                <div class="feature-icon">📊</div>
                                <div class="feature-content">
                                    <h4>Analytics Dashboard</h4>
                                    <p>Comprehensive data visualization for informed decision-making and system optimization</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mission-statement">
                            <h4>Our Commitment</h4>
                            <p>FireGuard is dedicated to enhancing public safety through innovative technology solutions. We collaborate with fire departments, emergency services, and community organizations to create resilient, well-prepared environments that prioritize life safety and property protection.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.about {
    padding: 60px 0;
    background: #ffffff;
    position: relative;
    overflow: hidden;
}

.about::before {
    display: none;
}

@keyframes grid-move {
    0% { transform: translate(0, 0); }
    100% { transform: translate(10px, 10px); }
}

.about-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    position: relative;
    z-index: 2;
}

.about-label {
    display: inline-block;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #ff6b35;
    margin-bottom: 10px;
    padding: 6px 15px;
    background: rgba(255, 107, 53, 0.1);
    border-radius: 10px;
    border: 1px solid rgba(255, 107, 53, 0.2);
}

.about-title {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.2;
    color: #2c3e50;
    margin: 0 0 10px 0;
}

.about-subtitle {
    font-size: 1rem;
    color: #6c757d;
    margin: 0;
    font-weight: 400;
}

.about-card {
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #f0f0f0;
    overflow: hidden;
    position: relative;
    animation: slideInUp 1s ease-out 0.3s both;
    transition: all 0.3s ease;
}

.about-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #ff6b35, #ff8c42, #ffa726, #ffcc02);
    animation: gradient-shift 3s ease-in-out infinite;
}

@keyframes gradient-shift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.about-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.card-content {
    padding: 30px;
}

.card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 107, 53, 0.1);
    animation: fadeInUp 0.8s ease-out 0.3s both;
}

.header-content {
    flex: 1;
    text-align: left;
}

.card-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #ff6b35, #ff8c42);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 20px;
    animation: pulse-glow 2s ease-in-out infinite;
    flex-shrink: 0;
}

.card-icon svg {
    width: 30px;
    height: 30px;
    color: white;
    animation: rotate-star 3s linear infinite;
}

@keyframes rotate-star {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes pulse-glow {
    0%, 100% { 
        transform: scale(1);
    }
    50% { 
        transform: scale(1.05);
    }
}

.card-body {
    animation: fadeInUp 0.8s ease-out 0.7s both;
}

.card-description {
    font-size: 1rem;
    line-height: 1.6;
    color: #5a6c7d;
    margin-bottom: 25px;
    text-align: justify;
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.feature-item {
    display: flex;
    align-items: flex-start;
    padding: 15px;
    background: #ffffff;
    border-radius: 10px;
    border: 1px solid #f0f0f0;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    animation: fadeInUp 0.8s ease-out both;
}

.feature-item:nth-child(1) { animation-delay: 0.9s; }
.feature-item:nth-child(2) { animation-delay: 1.0s; }
.feature-item:nth-child(3) { animation-delay: 1.1s; }
.feature-item:nth-child(4) { animation-delay: 1.2s; }

.feature-item:hover {
    background: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.feature-icon {
    font-size: 1.5rem;
    margin-right: 12px;
    animation: bounce-icon 2s ease-in-out infinite;
}

.feature-item:nth-child(1) .feature-icon { animation-delay: 0s; }
.feature-item:nth-child(2) .feature-icon { animation-delay: 0.5s; }
.feature-item:nth-child(3) .feature-icon { animation-delay: 1s; }
.feature-item:nth-child(4) .feature-icon { animation-delay: 1.5s; }

@keyframes bounce-icon {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.feature-content h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 6px 0;
}

.feature-content p {
    font-size: 0.85rem;
    line-height: 1.5;
    color: #6c757d;
    margin: 0;
}

.mission-statement {
    background: #ffffff;
    padding: 20px;
    border-radius: 10px;
    border-left: 3px solid #ff6b35;
    border: 1px solid #f0f0f0;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    animation: fadeInUp 0.8s ease-out 1.3s both;
}

.mission-statement h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 10px 0;
}

.mission-statement p {
    font-size: 0.9rem;
    line-height: 1.6;
    color: #5a6c7d;
    margin: 0;
}

/* Animation Keyframes */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .about {
        padding: 40px 0;
    }
    
    .about-content {
        padding: 0 15px;
    }
    
    .about-title {
        font-size: 1.6rem;
    }
    
    .about-subtitle {
        font-size: 0.9rem;
    }
    
    .card-content {
        padding: 20px;
    }
    
    .card-header {
        flex-direction: column;
        text-align: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
    }
    
    .header-content {
        text-align: center;
        margin-bottom: 15px;
    }
    
    .card-icon {
        margin-left: 0;
        margin-top: 10px;
        width: 50px;
        height: 50px;
    }
    
    .card-icon svg {
        width: 25px;
        height: 25px;
    }
    
    .feature-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .feature-item {
        padding: 12px;
    }
    
    .mission-statement {
        padding: 15px;
    }
}

@media (max-width: 480px) {
    .about {
        padding: 30px 0;
    }
    
    .about-title {
        font-size: 1.4rem;
    }
    
    .about-subtitle {
        font-size: 0.85rem;
    }
    
    .card-content {
        padding: 15px;
    }
    
    .card-header {
        padding-bottom: 15px;
    }
    
    .card-icon {
        width: 45px;
        height: 45px;
    }
    
    .card-icon svg {
        width: 22px;
        height: 22px;
    }
    
    .card-description {
        font-size: 0.9rem;
    }
    
    .feature-item {
        padding: 10px;
    }
    
    .feature-icon {
        font-size: 1.2rem;
    }
    
    .feature-content h4 {
        font-size: 0.9rem;
    }
    
    .feature-content p {
        font-size: 0.8rem;
    }
    
    .mission-statement {
        padding: 12px;
    }
    
    .mission-statement h4 {
        font-size: 1rem;
    }
    
    .mission-statement p {
        font-size: 0.85rem;
    }
}
</style>

<script>
// Enhanced About Section Interactions
document.addEventListener('DOMContentLoaded', function() {
    // Intersection Observer for animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);

    // Observe all animated elements
    const animatedElements = document.querySelectorAll('.about-header, .about-card, .feature-item, .mission-statement');
    animatedElements.forEach(el => observer.observe(el));

    // Add hover effects to feature items
    const featureItems = document.querySelectorAll('.feature-item');
    featureItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Add click effect to the main card
    const aboutCard = document.querySelector('.about-card');
    if (aboutCard) {
        aboutCard.addEventListener('click', function() {
            // Add ripple effect
            const ripple = document.createElement('div');
            ripple.style.position = 'absolute';
            ripple.style.borderRadius = '50%';
            ripple.style.background = 'rgba(255, 107, 53, 0.3)';
            ripple.style.transform = 'scale(0)';
            ripple.style.animation = 'ripple 0.6s linear';
            ripple.style.left = '50%';
            ripple.style.top = '50%';
            ripple.style.width = '20px';
            ripple.style.height = '20px';
            ripple.style.marginLeft = '-10px';
            ripple.style.marginTop = '-10px';
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    }

    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        .animate-in {
            animation-play-state: running;
        }
    `;
    document.head.appendChild(style);
});

// Text-to-Speech functionality for accessibility
class TextToSpeech {
    constructor() {
        this.speechSynthesis = window.speechSynthesis;
        this.speaking = false;
        this.voice = null;
        this.initVoice();
    }

    initVoice() {
        if (this.speechSynthesis.onvoiceschanged !== undefined) {
            this.speechSynthesis.onvoiceschanged = () => {
                const voices = this.speechSynthesis.getVoices();
                this.voice = voices.find(voice => 
                    voice.lang.includes('en') && voice.name.includes('Google')
                ) || voices.find(voice => 
                    voice.lang.includes('en')
                ) || voices[0];
            };
        }
    }

    speak(text, options = {}) {
        if (this.speaking) {
            this.stop();
        }

        const utterance = new SpeechSynthesisUtterance(text);
        
        if (this.voice) {
            utterance.voice = this.voice;
        }

        utterance.rate = options.rate || 0.9;
        utterance.pitch = options.pitch || 1;
        utterance.volume = options.volume || 0.8;
        utterance.lang = options.lang || 'en-US';

        utterance.onstart = () => {
            this.speaking = true;
        };

        utterance.onend = () => {
            this.speaking = false;
        };

        utterance.onerror = (event) => {
            this.speaking = false;
            console.error('Speech error:', event.error);
        };

        this.speechSynthesis.speak(utterance);
    }

    stop() {
        if (this.speaking) {
            this.speechSynthesis.cancel();
            this.speaking = false;
        }
    }

    isSupported() {
        return 'speechSynthesis' in window;
    }
}

// Initialize Text-to-Speech
const tts = new TextToSpeech();

// Add accessibility features
document.addEventListener('DOMContentLoaded', function() {
    // Add keyboard navigation
    const aboutCard = document.querySelector('.about-card');
    if (aboutCard) {
        aboutCard.setAttribute('tabindex', '0');
        aboutCard.setAttribute('role', 'button');
        aboutCard.setAttribute('aria-label', 'About FireGuard - Click to learn more');
        
        aboutCard.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    }

    // Add screen reader support
    const featureItems = document.querySelectorAll('.feature-item');
    featureItems.forEach((item, index) => {
        item.setAttribute('role', 'article');
        item.setAttribute('aria-labelledby', `feature-title-${index}`);
        
        const title = item.querySelector('h4');
        if (title) {
            title.id = `feature-title-${index}`;
        }
    });
});
</script>

</body>
</html>

