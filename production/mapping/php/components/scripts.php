</div>
</div>
</div></div>       

<!-- PHP variables for JavaScript -->
<script>
    // PHP variables converted to JavaScript
    const buildings = <?php echo json_encode($buildings ?? []); ?>;
    const buildingAreas = <?php echo json_encode($buildingAreas ?? []); ?>;
    const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>;
    
    // Debug logging
    console.log('Buildings loaded:', buildings.length);
    console.log('Building areas loaded:', buildingAreas.length);
    console.log('User ID:', userId);
    
    // Initialize speech variables immediately
    window.speechEnabled = true;
    window.currentUtterance = null;
    window.lastSpokenText = '';
    
    // Simple speech function that works immediately
    window.speakText = function(text, priority = 'normal') {
        console.log('Speaking:', text);
        
        if (!window.speechEnabled) {
            console.log('Speech disabled, skipping:', text);
            return;
        }
        
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
            
            const utterance = new SpeechSynthesisUtterance(text);
            window.currentUtterance = utterance;
            window.lastSpokenText = text;
            
            utterance.rate = 0.9;
            utterance.pitch = 1.0;
            utterance.volume = 1.0;
            
            const voices = speechSynthesis.getVoices();
            const englishVoice = voices.find(voice => 
                voice.lang.startsWith('en') && voice.name.includes('Google')
            ) || voices.find(voice => voice.lang.startsWith('en')) || voices[0];
            
            if (englishVoice) {
                utterance.voice = englishVoice;
            }
            
            if (priority === 'high') {
                utterance.rate = 0.8;
                utterance.volume = 1.0;
            }
            
            utterance.onstart = function() {
                console.log('Speech started:', text);
            };
            
            utterance.onend = function() {
                window.currentUtterance = null;
                console.log('Speech ended');
            };
            
            utterance.onerror = function(event) {
                console.error('Speech error:', event.error);
                window.currentUtterance = null;
            };
            
            try {
                window.speechSynthesis.speak(utterance);
                console.log('Speech synthesis speak() called successfully');
            } catch (error) {
                console.error('Error calling speechSynthesis.speak():', error);
            }
        } else {
            console.warn('Speech synthesis not supported in this browser');
        }
    };
    
    // RouteToStation function will be handled by main script.js
    console.log('Speech system ready - RouteToStation handled by main script');
    
    console.log('Speech functions initialized immediately');
    
    // Test speech function for debugging
    window.testSpeech = function() {
        console.log('Testing speech...');
        window.speakText('Speech test. If you can hear this, text-to-speech is working correctly.', 'normal');
    };
    
    // No automatic speech on page load - only for RouteToStation
    console.log('Speech system ready for RouteToStation only');
    console.log('To test speech, run: testSpeech() in console');
</script>

<script src="../js/script.js?v=<?php echo time(); ?>"></script>
<!-- Removed duplicate script inclusion to prevent conflicts -->

</body>
</html> 
<?php include '../../components/scripts.php'; ?>