/**
 * BLACK LIZARD // SCROLL ANIMATION & INTERACTIVE EXPERIENCE ENGINE
 * High-performance Canvas Frame Sequence Player with Smooth Lerp, Autoplay & Story Milestones
 */

(function () {
    'use strict';

    const TOTAL_FRAMES = 210;
    const FRAME_PATH_PREFIX = 'frames/ezgif-frame-';
    const FRAME_EXT = '.jpg';

    // State
    const state = {
        loadedCount: 0,
        isLoaded: false,
        currentFrame: 1,
        targetFrame: 1,
        isPlaying: false,
        playbackSpeed: 1,
        fitMode: 'cover', // 'cover' or 'contain'
        selectedSize: 'L',
        muted: false
    };

    // Frame cache
    const frames = new Array(TOTAL_FRAMES + 1);

    // DOM Elements
    let canvas, ctx;
    let trackEl, stickyContainerEl;
    let preloaderEl, preloaderBar, preloaderPercent;
    let frameCounterEl, scrubberFill, scrubberTime, playBtn, soundBtn;
    let storyCardAct1, storyCardAct2, storyCardAct3, storyCardAct4;
    let scrollHintEl;
    let turntableImg, turntableSlider, turntableLabel;

    // Web Audio Click Synthesizer
    let audioCtx = null;
    function playMicroClick() {
        if (state.muted) return;
        try {
            if (!audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) audioCtx = new AudioContext();
            }
            if (audioCtx && audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            if (audioCtx) {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(800, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(300, audioCtx.currentTime + 0.03);
                gain.gain.setValueAtTime(0.04, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.03);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.03);
            }
        } catch (e) {
            // Audio policy fallback
        }
    }

    // Helper: Pad frame number with leading zeroes (001, 002, ... 210)
    function getFrameUrl(index) {
        const padded = String(index).padStart(3, '0');
        return `${FRAME_PATH_PREFIX}${padded}${FRAME_EXT}`;
    }

    // Initialize DOM References
    function initDOMElements() {
        canvas = document.getElementById('scrollCanvas');
        if (!canvas) return false;
        ctx = canvas.getContext('2d', { alpha: false });

        trackEl = document.getElementById('scrollTrack');
        stickyContainerEl = document.getElementById('stickyContainer');

        preloaderEl = document.getElementById('animPreloader');
        preloaderBar = document.getElementById('animPreloaderBar');
        preloaderPercent = document.getElementById('animPreloaderPercent');

        frameCounterEl = document.getElementById('scrollFrameCounter');
        scrubberFill = document.getElementById('scrubberFill');
        scrubberTime = document.getElementById('scrubberTime');
        playBtn = document.getElementById('hudPlayBtn');
        soundBtn = document.getElementById('hudSoundBtn');

        storyCardAct1 = document.getElementById('storyCardAct1');
        storyCardAct2 = document.getElementById('storyCardAct2');
        storyCardAct3 = document.getElementById('storyCardAct3');
        storyCardAct4 = document.getElementById('storyCardAct4');

        scrollHintEl = document.getElementById('scrollIndicatorHint');

        turntableImg = document.getElementById('turntableImg');
        turntableSlider = document.getElementById('turntableSlider');
        turntableLabel = document.getElementById('turntableLabel');

        return true;
    }

    // Preload All 210 Frames
    function preloadFrames() {
        let loaded = 0;
        const total = TOTAL_FRAMES;

        for (let i = 1; i <= total; i++) {
            const img = new Image();
            img.src = getFrameUrl(i);
            img.onload = () => {
                loaded++;
                state.loadedCount = loaded;
                frames[i] = img;

                const percent = Math.min(100, Math.round((loaded / total) * 100));
                if (preloaderBar) preloaderBar.style.width = `${percent}%`;
                if (preloaderPercent) preloaderPercent.textContent = `${percent}%`;

                // If first frame loaded, draw immediately
                if (i === 1) {
                    resizeCanvas();
                    renderFrame(1);
                }

                if (loaded >= total || (loaded >= 35 && !state.isLoaded)) {
                    finishPreload();
                }
            };
            img.onerror = () => {
                loaded++;
                if (loaded >= total) finishPreload();
            };
        }
    }

    function finishPreload() {
        if (state.isLoaded) return;
        state.isLoaded = true;
        setTimeout(() => {
            if (preloaderEl) {
                preloaderEl.classList.add('fade-out');
                setTimeout(() => preloaderEl.style.display = 'none', 800);
            }
        }, 300);
    }

    // Resize Canvas with Retina HiDPI
    function resizeCanvas() {
        if (!canvas) return;
        const dpr = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        const displayWidth = rect.width || window.innerWidth;
        const displayHeight = rect.height || window.innerHeight;

        const targetW = Math.round(displayWidth * dpr);
        const targetH = Math.round(displayHeight * dpr);

        if (canvas.width !== targetW || canvas.height !== targetH) {
            canvas.width = targetW;
            canvas.height = targetH;
        }

        renderFrame(Math.round(state.currentFrame));
    }

    // Draw frame with Cover or Contain scaling
    let lastRenderedFrame = -1;
    function renderFrame(frameIndex) {
        if (!ctx || !canvas) return;
        frameIndex = Math.max(1, Math.min(TOTAL_FRAMES, Math.round(frameIndex)));

        // Retrieve cached image or fallback to nearest available
        let img = frames[frameIndex];
        if (!img || !img.complete) {
            for (let offset = 1; offset < 30; offset++) {
                if (frames[frameIndex - offset]?.complete) {
                    img = frames[frameIndex - offset];
                    break;
                } else if (frames[frameIndex + offset]?.complete) {
                    img = frames[frameIndex + offset];
                    break;
                }
            }
        }
        if (!img || !img.complete) return;

        const cw = canvas.width;
        const ch = canvas.height;
        const iw = img.naturalWidth || img.width;
        const ih = img.naturalHeight || img.height;

        ctx.fillStyle = '#050507';
        ctx.fillRect(0, 0, cw, ch);

        const imgRatio = iw / ih;
        const canvasRatio = cw / ch;

        let dw, dh, dx, dy;

        if (state.fitMode === 'cover') {
            if (canvasRatio > imgRatio) {
                dw = cw;
                dh = cw / imgRatio;
                dx = 0;
                dy = (ch - dh) / 2;
            } else {
                dh = ch;
                dw = ch * imgRatio;
                dx = (cw - dw) / 2;
                dy = 0;
            }
        } else {
            // contain
            if (canvasRatio > imgRatio) {
                dh = ch;
                dw = ch * imgRatio;
                dx = (cw - dw) / 2;
                dy = 0;
            } else {
                dw = cw;
                dh = cw / imgRatio;
                dx = 0;
                dy = (ch - dh) / 2;
            }
        }

        ctx.drawImage(img, dx, dy, dw, dh);
        lastRenderedFrame = frameIndex;

        // Update HUD
        updateHUD(frameIndex);
    }

    // Update Floating HUD & Story Milestones
    function updateHUD(frameIndex) {
        if (frameCounterEl) {
            const padded = String(frameIndex).padStart(3, '0');
            frameCounterEl.innerHTML = `FRAME <span class="highlight">${padded}</span> / ${TOTAL_FRAMES}`;
        }

        // Scrubber progress
        const progressPct = ((frameIndex - 1) / (TOTAL_FRAMES - 1)) * 100;
        if (scrubberFill) scrubberFill.style.width = `${progressPct}%`;
        if (scrubberTime) {
            const seconds = ((frameIndex / 30)).toFixed(1);
            scrubberTime.textContent = `00:${seconds.padStart(4, '0')}s`;
        }

        // Active Chapter Pill
        document.querySelectorAll('.chapter-pill').forEach(pill => {
            const target = parseInt(pill.dataset.frame, 10);
            const range = parseInt(pill.dataset.range || '50', 10);
            if (Math.abs(frameIndex - target) <= range) {
                pill.classList.add('active');
            } else {
                pill.classList.remove('active');
            }
        });

        // Hide scroll hint once user moves past frame 8
        if (scrollHintEl) {
            if (frameIndex > 8) {
                scrollHintEl.classList.add('hidden');
            } else {
                scrollHintEl.classList.remove('hidden');
            }
        }

        // Story Milestones Activations
        // Act 1: 1 - 48
        // Act 2: 49 - 108
        // Act 3: 109 - 162
        // Act 4: 163 - 210
        if (storyCardAct1) storyCardAct1.classList.toggle('active', frameIndex >= 1 && frameIndex <= 48);
        if (storyCardAct2) storyCardAct2.classList.toggle('active', frameIndex >= 50 && frameIndex <= 108);
        if (storyCardAct3) storyCardAct3.classList.toggle('active', frameIndex >= 110 && frameIndex <= 162);
        if (storyCardAct4) storyCardAct4.classList.toggle('active', frameIndex >= 165 && frameIndex <= 210);

        // Update Turntable widget if present
        if (turntableSlider && document.activeElement !== turntableSlider) {
            turntableSlider.value = frameIndex;
            if (turntableLabel) turntableLabel.textContent = `FRAME ${frameIndex}`;
        }
    }

    // Scroll Position Calculation
    function onScroll() {
        if (state.isPlaying) return; // Autoplay manages frame directly
        if (!trackEl) return;

        const rect = trackEl.getBoundingClientRect();
        const totalScrollable = rect.height - window.innerHeight;
        if (totalScrollable <= 0) return;

        // Current scroll offset relative to top of track
        const scrolled = -rect.top;
        const progress = Math.max(0, Math.min(1, scrolled / totalScrollable));

        state.targetFrame = 1 + progress * (TOTAL_FRAMES - 1);
    }

    // Smooth RAF Animation Loop (Spring Lerping)
    let lastTime = 0;
    function loop(time) {
        if (state.isPlaying) {
            // Autoplay mode
            const dt = (time - lastTime) / 1000 || 0.016;
            const fps = 28 * state.playbackSpeed;
            state.currentFrame += fps * dt;
            if (state.currentFrame > TOTAL_FRAMES) {
                state.currentFrame = 1;
            }
            state.targetFrame = state.currentFrame;
            renderFrame(Math.round(state.currentFrame));
        } else {
            // Scroll lerp mode
            const diff = state.targetFrame - state.currentFrame;
            if (Math.abs(diff) > 0.05) {
                state.currentFrame += diff * 0.16; // Smooth damping
                const rounded = Math.round(state.currentFrame);
                if (rounded !== lastRenderedFrame) {
                    renderFrame(rounded);
                }
            }
        }
        lastTime = time;
        requestAnimationFrame(loop);
    }

    // Autoplay Toggle
    function togglePlay() {
        state.isPlaying = !state.isPlaying;
        if (playBtn) {
            playBtn.innerHTML = state.isPlaying
                ? '<i class="fa-solid fa-pause"></i>'
                : '<i class="fa-solid fa-play"></i>';
            playBtn.classList.toggle('active', state.isPlaying);
        }
        playMicroClick();
    }

    // Jump to Frame (with optional scroll sync)
    function jumpToFrame(frameIndex, syncScroll = true) {
        frameIndex = Math.max(1, Math.min(TOTAL_FRAMES, frameIndex));
        state.targetFrame = frameIndex;
        state.currentFrame = frameIndex;
        renderFrame(frameIndex);
        playMicroClick();

        if (syncScroll && trackEl) {
            const rect = trackEl.getBoundingClientRect();
            const totalScrollable = rect.height - window.innerHeight;
            const progress = (frameIndex - 1) / (TOTAL_FRAMES - 1);
            const targetY = window.pageYOffset + rect.top + (progress * totalScrollable);
            window.scrollTo({ top: targetY, behavior: 'smooth' });
        }
    }

    // Quick Size Selector
    function setupSizeSelector() {
        document.querySelectorAll('.size-pill-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.size-pill-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.selectedSize = btn.dataset.size || btn.textContent.trim();
                playMicroClick();
            });
        });
    }

    // Add Black Lizard to Bag
    function setupBuyActions() {
        const buyBtns = document.querySelectorAll('.action-add-lizard-bag');
        buyBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                playMicroClick();

                // Check if Black Stone cart engine exists
                if (typeof window.addToCart === 'function') {
                    window.addToCart('bs-tee-lizard');
                } else {
                    // Fallback to storing in localStorage cart
                    let cart = [];
                    try {
                        cart = JSON.parse(localStorage.getItem('blackstone_cart') || '[]');
                    } catch (err) {
                        cart = [];
                    }
                    const existing = cart.find(item => item.id === 'bs-tee-lizard');
                    if (existing) {
                        existing.qty = (existing.qty || 1) + 1;
                    } else {
                        cart.push({
                            id: 'bs-tee-lizard',
                            name: 'Black Lizard Crimson Edition 280GSM Tee',
                            price: 58.00,
                            size: state.selectedSize,
                            image: 'frames/ezgif-frame-210.jpg',
                            qty: 1
                        });
                    }
                    localStorage.setItem('blackstone_cart', JSON.stringify(cart));
                    
                    // Trigger UI cart open if available
                    if (typeof window.toggleCart === 'function') {
                        window.toggleCart(true);
                    } else {
                        alert(`Added "Black Lizard Crimson Edition (${state.selectedSize})" to bag!`);
                    }
                }

                // Visual button confirmation
                const origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Added to Bag!';
                btn.style.background = '#00ff88';
                btn.style.color = '#000000';
                setTimeout(() => {
                    btn.innerHTML = origHtml;
                    btn.style.background = '';
                    btn.style.color = '';
                }, 2200);
            });
        });
    }

    // Bind Controls
    function setupListeners() {
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', resizeCanvas);

        // Play/Pause button
        if (playBtn) playBtn.addEventListener('click', togglePlay);

        // Sound Toggle
        if (soundBtn) {
            soundBtn.addEventListener('click', () => {
                state.muted = !state.muted;
                soundBtn.innerHTML = state.muted
                    ? '<i class="fa-solid fa-volume-xmark"></i>'
                    : '<i class="fa-solid fa-volume-high"></i>';
                soundBtn.classList.toggle('active', !state.muted);
                playMicroClick();
            });
        }

        // Speed Toggle
        const speedBtn = document.getElementById('hudSpeedBtn');
        if (speedBtn) {
            speedBtn.addEventListener('click', () => {
                if (state.playbackSpeed === 1) state.playbackSpeed = 1.5;
                else if (state.playbackSpeed === 1.5) state.playbackSpeed = 2;
                else state.playbackSpeed = 1;
                speedBtn.textContent = `${state.playbackSpeed}x`;
                playMicroClick();
            });
        }

        // Fit Mode Toggle
        const fitBtn = document.getElementById('hudFitBtn');
        if (fitBtn) {
            fitBtn.addEventListener('click', () => {
                state.fitMode = (state.fitMode === 'cover') ? 'contain' : 'cover';
                fitBtn.innerHTML = (state.fitMode === 'cover')
                    ? '<i class="fa-solid fa-expand"></i>'
                    : '<i class="fa-solid fa-compress"></i>';
                renderFrame(Math.round(state.currentFrame));
                playMicroClick();
            });
        }

        // Fullscreen Toggle
        const fsBtn = document.getElementById('hudFullscreenBtn');
        if (fsBtn) {
            fsBtn.addEventListener('click', () => {
                if (!document.fullscreenElement) {
                    if (stickyContainerEl && stickyContainerEl.requestFullscreen) {
                        stickyContainerEl.requestFullscreen();
                    } else if (document.documentElement.requestFullscreen) {
                        document.documentElement.requestFullscreen();
                    }
                } else {
                    if (document.exitFullscreen) document.exitFullscreen();
                }
                playMicroClick();
            });
        }

        // Chapter Jump Pills
        document.querySelectorAll('.chapter-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                const target = parseInt(pill.dataset.frame, 10);
                jumpToFrame(target, true);
            });
        });

        // Scrubber Track Click & Drag
        const scrubberTrack = document.getElementById('hudScrubberTrack');
        if (scrubberTrack) {
            let isDragging = false;
            const handleScrub = (e) => {
                const rect = scrubberTrack.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
                const target = Math.round(1 + ratio * (TOTAL_FRAMES - 1));
                jumpToFrame(target, false);
            };

            scrubberTrack.addEventListener('mousedown', (e) => {
                isDragging = true;
                handleScrub(e);
            });
            window.addEventListener('mousemove', (e) => {
                if (isDragging) handleScrub(e);
            });
            window.addEventListener('mouseup', () => { isDragging = false; });

            scrubberTrack.addEventListener('touchstart', (e) => {
                isDragging = true;
                handleScrub(e);
            }, { passive: true });
            window.addEventListener('touchmove', (e) => {
                if (isDragging) handleScrub(e);
            }, { passive: true });
            window.addEventListener('touchend', () => { isDragging = false; });
        }

        // Turntable Slider (interactive inspect section)
        if (turntableSlider) {
            turntableSlider.addEventListener('input', (e) => {
                const f = parseInt(e.target.value, 10);
                if (turntableImg) turntableImg.src = getFrameUrl(f);
                if (turntableLabel) turntableLabel.textContent = `FRAME ${f}`;
                playMicroClick();
            });
        }

        setupSizeSelector();
        setupBuyActions();
    }

    // Initialize Engine
    function init() {
        if (!initDOMElements()) return;
        preloadFrames();
        setupListeners();
        requestAnimationFrame(loop);
        onScroll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose Global API for Cross-Page or External Controls
    window.BlackLizardPlayer = {
        jumpToFrame,
        togglePlay,
        renderFrame,
        getState: () => ({ ...state })
    };

})();
