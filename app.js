/**
 * BLACK STONE LUXURY E-COMMERCE & MYSTERY BOX ENGINE (app.js)
 * Black & Crimson Red Streetwear Edition + Owner Product Portal + Interactive Size Chart
 */

// ==========================================
// 1. Audio System (Web Audio API Synthesizer)
// ==========================================
class BlackStoneAudio {
    constructor() {
        this.ctx = null;
        this.enabled = true;
    }

    init() {
        if (!this.ctx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                this.ctx = new AudioContext();
            }
        }
        if (this.ctx && this.ctx.state === 'suspended') {
            this.ctx.resume();
        }
    }

    playClick() {
        if (!this.enabled) return;
        this.init();
        if (!this.ctx) return;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(600, this.ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(1200, this.ctx.currentTime + 0.05);
        gain.gain.setValueAtTime(0.08, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.06);
        osc.start();
        osc.stop(this.ctx.currentTime + 0.06);
    }

    playDrumroll(durationMs = 1500) {
        if (!this.enabled) return;
        this.init();
        if (!this.ctx) return;

        const interval = 60;
        const total = Math.floor(durationMs / interval);
        let count = 0;

        const timer = setInterval(() => {
            if (count >= total) {
                clearInterval(timer);
                return;
            }
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            osc.connect(gain);
            gain.connect(this.ctx.destination);
            osc.type = 'triangle';
            const freq = 120 + (count / total) * 180;
            osc.frequency.setValueAtTime(freq, this.ctx.currentTime);
            gain.gain.setValueAtTime(0.06 + (count / total) * 0.05, this.ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.05);
            osc.start();
            osc.stop(this.ctx.currentTime + 0.05);
            count++;
        }, interval);
    }

    playUnboxFanfare() {
        if (!this.enabled) return;
        this.init();
        if (!this.ctx) return;

        // Chime arpeggio
        const notes = [523.25, 659.25, 783.99, 1046.50, 1318.51];
        notes.forEach((freq, idx) => {
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            osc.connect(gain);
            gain.connect(this.ctx.destination);
            osc.type = 'sine';
            const startTime = this.ctx.currentTime + idx * 0.09;
            osc.frequency.setValueAtTime(freq, startTime);
            gain.gain.setValueAtTime(0.12, startTime);
            gain.gain.exponentialRampToValueAtTime(0.0001, startTime + 0.4);
            osc.start(startTime);
            osc.stop(startTime + 0.4);
        });
    }

    playSuccess() {
        if (!this.enabled) return;
        this.init();
        if (!this.ctx) return;
        const notes = [440, 554.37, 659.25];
        notes.forEach((freq, idx) => {
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            osc.connect(gain);
            gain.connect(this.ctx.destination);
            osc.type = 'triangle';
            const startTime = this.ctx.currentTime + idx * 0.07;
            osc.frequency.setValueAtTime(freq, startTime);
            gain.gain.setValueAtTime(0.08, startTime);
            gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.25);
            osc.start(startTime);
            osc.stop(startTime + 0.25);
        });
    }

    playError() {
        if (!this.enabled) return;
        this.init();
        if (!this.ctx) return;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(180, this.ctx.currentTime);
        osc.frequency.linearRampToValueAtTime(110, this.ctx.currentTime + 0.2);
        gain.gain.setValueAtTime(0.08, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.2);
        osc.start();
        osc.stop(this.ctx.currentTime + 0.2);
    }
}

const bsAudio = new BlackStoneAudio();

// ==========================================
// 2. Confetti & Red Embers Particle FX Engine
// ==========================================
class ConfettiEngine {
    constructor(canvasId = 'confettiCanvas') {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) {
            this.canvas = document.createElement('canvas');
            this.canvas.id = canvasId;
            document.body.appendChild(this.canvas);
        }
        this.ctx = this.canvas.getContext('2d');
        this.particles = [];
        this.animationId = null;
        this.resize();
        window.addEventListener('resize', () => this.resize());
    }

    resize() {
        this.canvas.width = window.innerWidth;
        this.canvas.height = window.innerHeight;
    }

    burst(count = 80) {
        // Vibrant Black & Red Theme Colors (Crimson, Ruby, White, Blood Red)
        const colors = ['#ff1438', '#ff2e50', '#ffffff', '#8b0000', '#ff0033', '#ffa3b1'];
        const centerX = window.innerWidth / 2;
        const centerY = window.innerHeight / 2;

        for (let i = 0; i < count; i++) {
            const angle = Math.random() * Math.PI * 2;
            const speed = 4 + Math.random() * 9;
            this.particles.push({
                x: centerX,
                y: centerY,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed - 2,
                color: colors[Math.floor(Math.random() * colors.length)],
                size: 4 + Math.random() * 5,
                rotation: Math.random() * 360,
                rotSpeed: (Math.random() - 0.5) * 15,
                opacity: 1,
                decay: 0.012 + Math.random() * 0.015
            });
        }

        if (!this.animationId) {
            this.render();
        }
    }

    render() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        for (let i = this.particles.length - 1; i >= 0; i--) {
            const p = this.particles[i];
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.18; // gravity
            p.rotation += p.rotSpeed;
            p.opacity -= p.decay;

            if (p.opacity <= 0) {
                this.particles.splice(i, 1);
                continue;
            }

            this.ctx.save();
            this.ctx.globalAlpha = p.opacity;
            this.ctx.translate(p.x, p.y);
            this.ctx.rotate((p.rotation * Math.PI) / 180);
            this.ctx.fillStyle = p.color;
            this.ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
            this.ctx.restore();
        }

        if (this.particles.length > 0) {
            this.animationId = requestAnimationFrame(() => this.render());
        } else {
            this.animationId = null;
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        }
    }
}

let confettiEngine = null;

// ==========================================
// 3. Products Catalog Data & Owner Storage
// ==========================================
const BS_CUSTOM_PRODUCTS_KEY = 'blackstone_custom_products_v1';

const BASE_BLACKSTONE_PRODUCTS = [
    {
        id: 'bs-tee-lizard',
        name: 'Black Lizard Crimson Edition 280GSM Tee',
        category: 'signature',
        price: 58.00,
        originalPrice: 75.00,
        badge: 'Flagship 3D Drop',
        rating: 5.0,
        reviews: 210,
        gsm: '280 GSM',
        quality: '280 GSM Heavyweight Organic Ring-Spun Cotton',
        fabric: '100% Combed Japanese Organic Ring-Spun Cotton, Pre-shrunk Bio-Wash',
        hasSizeChart: true,
        image: 'frames/ezgif-frame-210.jpg',
        description: 'The iconic Black Lizard flagship drop. Heavyweight 280GSM organic cotton featuring live morph silicone relief print and encrypted mystery code tag. Experience the full 3D interactive scroll animation drop.',
        colors: ['red', 'black'],
        sizes: ['S', 'M', 'L', 'XL', 'XXL']
    },
    {
        id: 'bs-tee-01',
        name: 'Signature Crimson Crest Heavy Tee',
        category: 'signature',
        price: 49.00,
        originalPrice: 65.00,
        badge: 'Core Drop',
        rating: 4.9,
        reviews: 142,
        gsm: '260 GSM',
        quality: '260 GSM Heavyweight Organic Combed Cotton',
        fabric: '100% Combed Japanese Organic Cotton, Pre-shrunk',
        hasSizeChart: true,
        image: 'assets/sig_tee.png',
        description: 'Our iconic flagship piece featuring metallic crimson embroidered Black Stone crest on a heavyweight 260GSM pre-shrunk cotton blank. Cut with a modern dropped shoulder and tailored ribbed collar.',
        colors: ['black', 'red', 'grey'],
        sizes: ['S', 'M', 'L', 'XL', 'XXL']
    },
    {
        id: 'bs-tee-02',
        name: 'Obsidian Monolith Boxy Tee',
        category: 'minimalist',
        price: 45.00,
        originalPrice: 58.00,
        badge: 'Raw Cut',
        rating: 4.8,
        reviews: 98,
        gsm: '280 GSM',
        quality: '280 GSM Mineral-Washed Dense Jersey',
        fabric: '280GSM Heavyweight Mineral-Washed Cotton',
        hasSizeChart: true,
        image: 'assets/tee_obsidian.png',
        description: 'Zero loud branding. Pure architectural silhouette. Crafted from dense 280GSM mineral-washed cotton for an ultra-structured, boxy drape that holds its architecture after countless wears.',
        colors: ['black', 'grey'],
        sizes: ['S', 'M', 'L', 'XL']
    },
    {
        id: 'bs-tee-03',
        name: 'Neo-Tokyo Crimson Glyph Tee',
        category: 'graphic',
        price: 55.00,
        originalPrice: 70.00,
        badge: 'Limited Drop',
        rating: 4.9,
        reviews: 86,
        gsm: '260 GSM',
        quality: '260 GSM Silk-Screened Compact Cotton',
        fabric: '260GSM High-Density Silk-Screened Cotton',
        hasSizeChart: true,
        image: 'assets/sig_tee.png',
        description: 'High-density blood red foil typographic screen print merging cyber-kanji typography with minimalist streetwear geometry. Includes an authentic label claim code for the Mystery Box.',
        colors: ['black', 'red'],
        sizes: ['M', 'L', 'XL', 'XXL']
    },
    {
        id: 'bs-tee-04',
        name: 'Vintage Acid Wash Heavy Tee',
        category: 'signature',
        price: 52.00,
        originalPrice: 68.00,
        badge: 'Bestseller',
        rating: 4.9,
        reviews: 114,
        gsm: '270 GSM',
        quality: '270 GSM Enzymatic Washed Cotton',
        fabric: 'Custom Enzymatic Acid Wash 270GSM Cotton',
        hasSizeChart: true,
        image: 'assets/tee_obsidian.png',
        description: 'Individual hand-dipped acid wash treatment guarantees that no two garments are identical. Subtle distress at the hem with reinforced twin-needle stitching.',
        colors: ['black', 'grey'],
        sizes: ['S', 'M', 'L', 'XL']
    },
    {
        id: 'bs-tee-05',
        name: 'Zenith Minimalist Collar Tee',
        category: 'minimalist',
        price: 42.00,
        originalPrice: 55.00,
        badge: 'Essential',
        rating: 4.7,
        reviews: 64,
        gsm: '250 GSM',
        quality: '250 GSM Ringspun Compact Cotton',
        fabric: '250GSM Combed Compact Ringspun Cotton',
        hasSizeChart: true,
        image: 'assets/tee_obsidian.png',
        description: 'Precision cut mock-neck collar with subtle tone-on-tone silicone Black Stone logo badge at the nape. The ultimate luxury layering essential.',
        colors: ['black', 'grey', 'white'],
        sizes: ['S', 'M', 'L', 'XL', 'XXL']
    },
    {
        id: 'bs-tee-06',
        name: 'Blood Wine Metallic Foil Edition',
        category: 'graphic',
        price: 62.00,
        originalPrice: 80.00,
        badge: 'VIP Exclusive',
        rating: 5.0,
        reviews: 43,
        gsm: '290 GSM',
        quality: '290 GSM Ultra-Heavyweight French Terry',
        fabric: '290GSM Ultra-Heavyweight French Terry Cotton',
        hasSizeChart: true,
        image: 'assets/sig_tee.png',
        description: 'Metallic ruby hot-stamped back piece depicting the sacred Black Stone geometry. Limited to only 150 individually numbered pieces worldwide.',
        colors: ['black', 'red'],
        sizes: ['M', 'L', 'XL']
    },
    {
        id: 'bs-tee-07',
        name: 'Royal Bloodline Heritage Crest Tee',
        category: 'signature',
        price: 58.00,
        originalPrice: 75.00,
        badge: 'Heritage',
        rating: 4.9,
        reviews: 79,
        gsm: '260 GSM',
        quality: '260 GSM Dense Weave Organic Cotton',
        fabric: '260GSM Dense Weave Organic Cotton',
        hasSizeChart: true,
        image: 'assets/sig_tee.png',
        description: 'Featuring the full Black Stone heritage crest with heraldic lion and laurel wreath in precision crimson embroidery. Finished with custom copper rivet on collar label.',
        colors: ['black', 'white'],
        sizes: ['S', 'M', 'L', 'XL', 'XXL']
    },
    {
        id: 'bs-tee-08',
        name: 'Architectural Shadow Grid Tee',
        category: 'graphic',
        price: 48.00,
        originalPrice: 62.00,
        badge: 'New Arrival',
        rating: 4.8,
        reviews: 52,
        gsm: '260 GSM',
        quality: '260 GSM Compact Interlock Jersey',
        fabric: '260GSM Compact Jersey',
        hasSizeChart: true,
        image: 'assets/tee_obsidian.png',
        description: 'Subtle tone-on-tone grid print across the torso inspired by brutalist architecture. Modern relaxed streetwear silhouette with drop tail hem.',
        colors: ['black', 'grey'],
        sizes: ['S', 'M', 'L', 'XL']
    }
];

let BLACKSTONE_PRODUCTS = [...BASE_BLACKSTONE_PRODUCTS];

function loadCustomProducts() {
    try {
        const stored = localStorage.getItem(BS_CUSTOM_PRODUCTS_KEY);
        if (stored) {
            const customList = JSON.parse(stored);
            if (Array.isArray(customList) && customList.length > 0) {
                BLACKSTONE_PRODUCTS = [...customList, ...BASE_BLACKSTONE_PRODUCTS];
            }
        }
    } catch (e) {
        console.warn('Error loading custom products', e);
    }
}

function getCustomProducts() {
    try {
        const stored = localStorage.getItem(BS_CUSTOM_PRODUCTS_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch (e) {
        return [];
    }
}

function saveCustomProduct(product) {
    const list = getCustomProducts();
    list.unshift(product);
    try {
        localStorage.setItem(BS_CUSTOM_PRODUCTS_KEY, JSON.stringify(list));
    } catch (e) {
        console.warn('Error saving custom product', e);
    }
    loadCustomProducts();
    renderProducts();
}

// Initial load
loadCustomProducts();

// ==========================================
// 4. Shopping Cart Store & Logic
// ==========================================
const BS_CART_KEY = 'blackstone_cart_v1';

function getCart() {
    try {
        const data = localStorage.getItem(BS_CART_KEY);
        if (data) return JSON.parse(data);
    } catch (e) {
        console.warn('Cart read error', e);
    }
    return [];
}

function saveCart(cart) {
    try {
        localStorage.setItem(BS_CART_KEY, JSON.stringify(cart));
    } catch (e) {
        console.warn('Cart write error', e);
    }
    renderCart();
}

function toggleCart(forceOpen = null) {
    const drawer = document.getElementById('cartDrawer');
    if (!drawer) return;

    if (forceOpen === true) {
        drawer.classList.add('active');
        document.body.style.overflow = 'hidden';
        bsAudio.playClick();
    } else if (forceOpen === false) {
        drawer.classList.remove('active');
        document.body.style.overflow = '';
        bsAudio.playClick();
    } else {
        const isActive = drawer.classList.toggle('active');
        document.body.style.overflow = isActive ? 'hidden' : '';
        bsAudio.playClick();
    }
}

function addToCart(productId, size = 'L', color = 'black', quantity = 1) {
    const product = BLACKSTONE_PRODUCTS.find(p => p.id === productId);
    if (!product) return;

    const cart = getCart();
    const existingIndex = cart.findIndex(item => item.id === productId && item.size === size && item.color === color);

    if (existingIndex > -1) {
        cart[existingIndex].quantity += quantity;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            image: product.image,
            size: size,
            color: color,
            quantity: quantity,
            gsm: product.gsm || '260 GSM'
        });
    }

    saveCart(cart);
    bsAudio.playSuccess();
    if (confettiEngine) confettiEngine.burst(30);
    toggleCart(true);
}

function quickAddToCart(productId, event) {
    if (event) event.stopPropagation();
    const product = BLACKSTONE_PRODUCTS.find(p => p.id === productId);
    const size = product && product.sizes ? product.sizes[0] : 'L';
    const color = product && product.colors ? product.colors[0] : 'black';
    addToCart(productId, size, color, 1);
}

function removeFromCart(index) {
    const cart = getCart();
    if (index >= 0 && index < cart.length) {
        cart.splice(index, 1);
        saveCart(cart);
        bsAudio.playClick();
    }
}

function updateCartQty(index, delta) {
    const cart = getCart();
    if (index >= 0 && index < cart.length) {
        cart[index].quantity += delta;
        if (cart[index].quantity <= 0) {
            cart.splice(index, 1);
        }
        saveCart(cart);
        bsAudio.playClick();
    }
}

function renderCart() {
    const cart = getCart();
    const totalCount = cart.reduce((acc, item) => acc + item.quantity, 0);
    const subtotal = cart.reduce((acc, item) => acc + (item.price * item.quantity), 0);

    const cartCountEls = document.querySelectorAll('#cartCount, .cart-count');
    cartCountEls.forEach(el => el.textContent = totalCount);

    const drawerCountEl = document.getElementById('cartDrawerCount');
    if (drawerCountEl) drawerCountEl.textContent = totalCount;

    const itemsListEl = document.getElementById('cartItemsList');
    const footerEl = document.getElementById('cartFooter');
    const totalValEl = document.getElementById('cartTotalVal');

    if (totalValEl) totalValEl.textContent = `$${subtotal.toFixed(2)}`;

    if (!itemsListEl) return;

    if (cart.length === 0) {
        itemsListEl.innerHTML = `
            <div class="empty-cart-message">
                <i class="fa-solid fa-bag-shopping" style="color: var(--color-border); font-size: 3rem;"></i>
                <p>Your luxury bag is currently empty.</p>
                <button class="btn btn-outline btn-sm mt-3" onclick="toggleCart(false); scrollToSection('shop');">
                    Explore Collection
                </button>
            </div>
        `;
        if (footerEl) footerEl.style.display = 'none';
    } else {
        if (footerEl) footerEl.style.display = 'block';
        itemsListEl.innerHTML = cart.map((item, idx) => `
            <div class="cart-item">
                <div class="cart-item-img-box">
                    <img src="${item.image}" alt="${item.name}">
                </div>
                <div class="cart-item-details">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div>
                            <div class="cart-item-title">${item.name}</div>
                            <div class="cart-item-option">Size: ${item.size} &bull; ${item.color.toUpperCase()} &bull; ${item.gsm}</div>
                        </div>
                        <span class="cart-item-remove" title="Remove Item" onclick="removeFromCart(${idx})">
                            <i class="fa-solid fa-trash-can"></i>
                        </span>
                    </div>
                    <div class="cart-item-bottom">
                        <div class="qty-selector">
                            <button class="qty-btn" onclick="updateCartQty(${idx}, -1)">-</button>
                            <span class="qty-num">${item.quantity}</span>
                            <button class="qty-btn" onclick="updateCartQty(${idx}, 1)">+</button>
                        </div>
                        <div class="cart-item-price">$${(item.price * item.quantity).toFixed(2)}</div>
                    </div>
                </div>
            </div>
        `).join('');
    }
}

// ==========================================
// 5. Checkout & Mystery Box Code Generator
// ==========================================
let currentGeneratedCodes = [];

function checkoutCart() {
    const cart = getCart();
    if (cart.length === 0) {
        alert('Your shopping bag is empty. Please add a product first.');
        return;
    }

    toggleCart(false);

    const randomOrderId = '#BS-' + Math.floor(10000 + Math.random() * 90000);
    const orderIdEl = document.getElementById('checkoutOrderId');
    if (orderIdEl) orderIdEl.textContent = randomOrderId;

    currentGeneratedCodes = [];
    const totalItems = cart.reduce((acc, item) => acc + item.quantity, 0);

    for (let i = 0; i < totalItems; i++) {
        const randomHash = Math.random().toString(36).substring(2, 7).toUpperCase();
        const code = `BLACKSTONE-${randomHash}`;
        currentGeneratedCodes.push(code);

        const rarities = ['epic', 'legendary', 'rare'];
        const rarity = rarities[Math.floor(Math.random() * rarities.length)];
        DEFAULT_DEMO_CODES[code] = {
            code: code,
            rarity: rarity,
            title: `${rarity.toUpperCase()} VIP Mystery Garment Reward`,
            prize: rarity === 'legendary' ? '₹1,500 Voucher + Custom Leather Tag' : (rarity === 'epic' ? '35% OFF + Sterling Silver Ring' : '25% OFF Next Drop Code'),
            desc: `Generated from order ${randomOrderId}. Verified authentic cotton label tag.`
        };
    }

    const codesListEl = document.getElementById('generatedCodesList');
    if (codesListEl) {
        codesListEl.innerHTML = currentGeneratedCodes.map(c => `
            <span class="generated-code-ticket" onclick="copySingleCode('${c}', this)" title="Click to copy">
                <i class="fa-solid fa-ticket-simple me-1"></i> ${c}
            </span>
        `).join('');
    }

    saveCart([]);
    const checkoutModal = document.getElementById('checkoutModal');
    if (checkoutModal) {
        checkoutModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    bsAudio.playUnboxFanfare();
    if (confettiEngine) confettiEngine.burst(80);
}

function copyAllGeneratedCodes() {
    if (currentGeneratedCodes.length === 0) return;
    const text = currentGeneratedCodes.join('\n');
    navigator.clipboard.writeText(text).then(() => {
        bsAudio.playClick();
        alert('All Mystery Box codes copied to clipboard! Paste them in the Code Zone to unlock rewards.');
    }).catch(() => {
        prompt('Copy your codes below:', text);
    });
}

function copySingleCode(code, element) {
    navigator.clipboard.writeText(code).then(() => {
        bsAudio.playClick();
        const originalText = element.innerHTML;
        element.innerHTML = `<i class="fa-solid fa-check me-1"></i> COPIED!`;
        element.style.borderColor = '#4ade80';
        element.style.color = '#4ade80';
        setTimeout(() => {
            element.innerHTML = originalText;
            element.style.borderColor = '';
            element.style.color = '';
        }, 1500);
    });
}

function closeCheckoutModalAndGoToCodes() {
    const modal = document.getElementById('checkoutModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    if (currentGeneratedCodes.length > 0) {
        sessionStorage.setItem('blackstone_prefill_code', currentGeneratedCodes[0]);
    }
    window.location.href = 'code-zone.html';
}

// ==========================================
// 6. Product Detail Quick View Modal
// ==========================================
let currentModalProductId = null;
let currentModalSelectedSize = 'L';
let currentModalSelectedColor = 'black';

function openProductModal(productId) {
    const product = BLACKSTONE_PRODUCTS.find(p => p.id === productId);
    if (!product) return;

    currentModalProductId = productId;
    currentModalSelectedSize = product.sizes && product.sizes[0] ? product.sizes[0] : 'L';
    currentModalSelectedColor = product.colors && product.colors[0] ? product.colors[0] : 'black';

    const modalGrid = document.getElementById('productModalGrid');
    if (!modalGrid) return;

    modalGrid.innerHTML = `
        <div class="product-modal-gallery">
            <img src="${product.image}" alt="${product.name}" id="modalProductImg">
        </div>
        <div class="product-modal-info">
            <div class="modal-product-tag">${product.badge} &bull; ${product.category} &bull; ${product.gsm}</div>
            <h2 class="modal-product-title">${product.name}</h2>
            <div class="modal-product-price">$${product.price.toFixed(2)} <span style="font-size:0.9rem; color:var(--color-muted); text-decoration:line-through; margin-left:8px;">$${product.originalPrice ? product.originalPrice.toFixed(2) : (product.price * 1.3).toFixed(2)}</span></div>
            <p class="modal-product-desc">${product.description}</p>
            <div style="font-size:0.85rem; color:var(--color-red); margin-bottom: 16px;">
                <i class="fa-solid fa-certificate me-1"></i> Quality: <strong>${product.quality || product.fabric || '260 GSM Premium Combed Cotton'}</strong>
            </div>

            <!-- Size Selector + Size Chart Action -->
            <div class="option-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <span class="option-label" style="margin-bottom:0;">Select Size</span>
                    <button type="button" class="btn btn-outline btn-sm" onclick="openSizeChartModal('${product.id}')" style="padding: 3px 10px; font-size: 0.75rem; border-color: var(--color-red); color: var(--color-red);">
                        <i class="fa-solid fa-ruler-combined me-1"></i> Size Chart
                    </button>
                </div>
                <div class="size-chips">
                    ${(product.sizes || ['S', 'M', 'L', 'XL']).map(sz => `
                        <div class="size-chip ${sz === currentModalSelectedSize ? 'active' : ''}" onclick="selectModalSize('${sz}', this)">${sz}</div>
                    `).join('')}
                </div>
            </div>

            <!-- Color Selector -->
            <div class="option-group">
                <div class="option-label">Garment Color</div>
                <div class="color-dots">
                    ${(product.colors || ['black']).map(col => `
                        <div class="color-dot ${col} ${col === currentModalSelectedColor ? 'active' : ''}" title="${col}" onclick="selectModalColor('${col}', this)" style="${col === 'red' ? 'background:#ff1438;' : ''}"></div>
                    `).join('')}
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="modal-product-ctas">
                <button class="btn btn-gold w-100 py-3" onclick="submitModalAddToCart()">
                    <i class="fa-solid fa-bag-shopping me-1"></i> Add To Bag
                </button>
            </div>
        </div>
    `;

    const modal = document.getElementById('productModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        bsAudio.playClick();
    }
}

function selectModalSize(size, el) {
    currentModalSelectedSize = size;
    const chips = el.parentElement.querySelectorAll('.size-chip');
    chips.forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    bsAudio.playClick();
}

function selectModalColor(color, el) {
    currentModalSelectedColor = color;
    const dots = el.parentElement.querySelectorAll('.color-dot');
    dots.forEach(d => d.classList.remove('active'));
    el.classList.add('active');
    bsAudio.playClick();
}

function submitModalAddToCart() {
    if (!currentModalProductId) return;
    addToCart(currentModalProductId, currentModalSelectedSize, currentModalSelectedColor, 1);
    closeProductModal();
}

function closeProductModal() {
    const modal = document.getElementById('productModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        bsAudio.playClick();
    }
}

// ==========================================
// 7. Interactive Size Chart Modal
// ==========================================
const SIZE_CHART_MEASUREMENTS = {
    inches: [
        { size: 'XS', chest: '40"', length: '27.5"', shoulder: '19.5"', sleeve: '8.0"' },
        { size: 'S',  chest: '42"', length: '28.5"', shoulder: '20.5"', sleeve: '8.5"' },
        { size: 'M',  chest: '44"', length: '29.5"', shoulder: '21.5"', sleeve: '9.0"' },
        { size: 'L',  chest: '46"', length: '30.5"', shoulder: '22.5"', sleeve: '9.5"' },
        { size: 'XL', chest: '48"', length: '31.5"', shoulder: '23.5"', sleeve: '10.0"' },
        { size: 'XXL',chest: '50"', length: '32.5"', shoulder: '24.5"', sleeve: '10.5"' },
        { size: '3XL',chest: '52"', length: '33.5"', shoulder: '25.5"', sleeve: '11.0"' }
    ],
    cm: [
        { size: 'XS', chest: '101 cm', length: '70 cm', shoulder: '49 cm', sleeve: '20 cm' },
        { size: 'S',  chest: '107 cm', length: '72 cm', shoulder: '52 cm', sleeve: '22 cm' },
        { size: 'M',  chest: '112 cm', length: '75 cm', shoulder: '55 cm', sleeve: '23 cm' },
        { size: 'L',  chest: '117 cm', length: '77 cm', shoulder: '57 cm', sleeve: '24 cm' },
        { size: 'XL', chest: '122 cm', length: '80 cm', shoulder: '60 cm', sleeve: '25 cm' },
        { size: 'XXL',chest: '127 cm', length: '82 cm', shoulder: '62 cm', sleeve: '27 cm' },
        { size: '3XL',chest: '132 cm', length: '85 cm', shoulder: '65 cm', sleeve: '28 cm' }
    ]
};

let currentSizeChartUnit = 'inches';

function openSizeChartModal(productId = null) {
    let modal = document.getElementById('sizeChartModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'sizeChartModal';
        modal.className = 'modal-backdrop';
        modal.innerHTML = `
            <div class="modal-content size-chart-modal-content">
                <button class="close-btn modal-close" onclick="closeSizeChartModal()"><i class="fa-solid fa-xmark"></i></button>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px; flex-wrap:wrap; gap:12px;">
                    <div>
                        <span class="section-tag" style="margin-bottom:4px;">Tailoring Guide</span>
                        <h2 style="font-family: var(--font-heading); font-size: 1.8rem;">Streetwear Size Matrix</h2>
                    </div>
                    <div class="size-unit-toggle">
                        <button class="size-unit-btn active" id="unitBtnIn" onclick="switchSizeChartUnit('inches')">INCHES (IN)</button>
                        <button class="size-unit-btn" id="unitBtnCm" onclick="switchSizeChartUnit('cm')">CENTIMETERS (CM)</button>
                    </div>
                </div>

                <div class="size-table-wrapper">
                    <table class="size-chart-table" id="sizeChartTableEl"></table>
                </div>

                <div class="size-chart-hint">
                    <strong><i class="fa-solid fa-circle-info me-1"></i> Cut & Drape Note:</strong>
                    All Black Stone T-shirts feature our signature <em>Heavyweight Dropped Shoulder Fit</em> with a relaxed chest and structured collar. Order your standard size for an effortless modern drape, or size down if you prefer a slim silhouette.
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    renderSizeChartTable();
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    bsAudio.playClick();
}

function switchSizeChartUnit(unit) {
    currentSizeChartUnit = unit;
    const btnIn = document.getElementById('unitBtnIn');
    const btnCm = document.getElementById('unitBtnCm');
    if (btnIn && btnCm) {
        btnIn.classList.toggle('active', unit === 'inches');
        btnCm.classList.toggle('active', unit === 'cm');
    }
    renderSizeChartTable();
    bsAudio.playClick();
}

function renderSizeChartTable() {
    const table = document.getElementById('sizeChartTableEl');
    if (!table) return;

    const data = SIZE_CHART_MEASUREMENTS[currentSizeChartUnit] || SIZE_CHART_MEASUREMENTS.inches;
    table.innerHTML = `
        <thead>
            <tr>
                <th>Size</th>
                <th>Chest Width</th>
                <th>Total Length</th>
                <th>Shoulder Width</th>
                <th>Sleeve Length</th>
            </tr>
        </thead>
        <tbody>
            ${data.map(row => `
                <tr>
                    <td><strong>${row.size}</strong></td>
                    <td>${row.chest}</td>
                    <td>${row.length}</td>
                    <td>${row.shoulder}</td>
                    <td>${row.sleeve}</td>
                </tr>
            `).join('')}
        </tbody>
    `;
}

function closeSizeChartModal() {
    const modal = document.getElementById('sizeChartModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        bsAudio.playClick();
    }
}

// ==========================================
// 8. Owner Product Listing System (Owner Hub)
// ==========================================
let ownerSelectedSizes = new Set(['S', 'M', 'L', 'XL']);
let ownerSelectedColors = new Set(['black', 'red']);
let ownerUploadedImageSrc = 'assets/sig_tee.png';

function openOwnerModal() {
    let modal = document.getElementById('ownerModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'ownerModal';
        modal.className = 'modal-backdrop';
        modal.innerHTML = `
            <div class="modal-content owner-modal-content">
                <button class="close-btn modal-close" onclick="closeOwnerModal()"><i class="fa-solid fa-xmark"></i></button>
                <div style="margin-bottom: 24px;">
                    <span class="section-tag"><i class="fa-solid fa-shield-halved me-1"></i> Store Manager</span>
                    <h2 style="font-family: var(--font-heading); font-size: 2rem;">List New Streetwear Product</h2>
                    <p style="color: var(--color-muted); font-size: 0.95rem;">
                        Add garments directly to your live Black Stone catalog with price, sizes, GSM quality, colors, size chart, and photos.
                    </p>
                </div>

                <form id="ownerListingForm" onsubmit="handleOwnerProductSubmit(event)">
                    <div class="owner-form-grid">
                        
                        <!-- Left Column -->
                        <div>
                            <!-- Product Title -->
                            <div class="form-group">
                                <label class="form-label">Product Title / Name *</label>
                                <input type="text" id="ownerTitle" class="form-control-custom" placeholder="e.g. Crimson Crest Heavy Tee" required>
                            </div>

                            <!-- Category & Badge -->
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div class="form-group">
                                    <label class="form-label">Collection Category</label>
                                    <select id="ownerCategory" class="form-control-custom" style="cursor: pointer;">
                                        <option value="signature">Signature</option>
                                        <option value="graphic">Graphic</option>
                                        <option value="minimalist">Minimalist</option>
                                        <option value="hoodies">Hoodies</option>
                                        <option value="limited">Limited Drop</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Drop Badge Tag</label>
                                    <input type="text" id="ownerBadge" class="form-control-custom" placeholder="e.g. HOT DROP, CORE">
                                </div>
                            </div>

                            <!-- Price & Original Price -->
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div class="form-group">
                                    <label class="form-label">Selling Price ($) *</label>
                                    <input type="number" id="ownerPrice" class="form-control-custom" step="0.01" min="1" placeholder="49.00" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Compare Price ($)</label>
                                    <input type="number" id="ownerOrigPrice" class="form-control-custom" step="0.01" placeholder="65.00">
                                </div>
                            </div>

                            <!-- Quality / Fabric Grade -->
                            <div class="form-group">
                                <label class="form-label">Fabric Quality & GSM *</label>
                                <div class="chips-selector" style="margin-bottom: 8px;">
                                    <button type="button" class="chip-select-btn active" onclick="selectOwnerQuality(this, '260 GSM Heavyweight Organic Cotton')">260 GSM</button>
                                    <button type="button" class="chip-select-btn" onclick="selectOwnerQuality(this, '280 GSM Mineral-Washed Dense Jersey')">280 GSM</button>
                                    <button type="button" class="chip-select-btn" onclick="selectOwnerQuality(this, '290 GSM Ultra-Heavy French Terry')">290 GSM</button>
                                    <button type="button" class="chip-select-btn" onclick="selectOwnerQuality(this, '240 GSM Compact Ringspun Cotton')">240 GSM</button>
                                </div>
                                <input type="text" id="ownerQualityInput" class="form-control-custom" value="260 GSM Heavyweight Organic Cotton" placeholder="e.g. 260 GSM 100% Combed Japanese Cotton" required>
                            </div>

                            <!-- Option of Size Chart -->
                            <div class="form-group" style="background: rgba(255,20,56,0.06); padding: 14px; border: 1px solid rgba(255,20,56,0.25); border-radius: var(--border-radius-sm);">
                                <label style="display:flex; align-items:center; gap: 10px; cursor: pointer; font-size: 0.9rem; font-weight: 600;">
                                    <input type="checkbox" id="ownerHasSizeChart" checked style="width: 18px; height: 18px; accent-color: var(--color-red);">
                                    <span>Enable Interactive Size Chart for this product</span>
                                </label>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 8px;">
                                    <span style="font-size: 0.78rem; color: var(--color-muted);">Includes S to 3XL Chest/Length measurements</span>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="openSizeChartModal()" style="padding: 2px 8px; font-size: 0.72rem;">Preview Chart</button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div>
                            <!-- Available Sizes -->
                            <div class="form-group">
                                <label class="form-label">Available Sizes (Click to toggle)</label>
                                <div class="chips-selector" id="ownerSizeChips">
                                    <button type="button" class="chip-select-btn" onclick="toggleOwnerSize(this, 'XS')">XS</button>
                                    <button type="button" class="chip-select-btn active" onclick="toggleOwnerSize(this, 'S')">S</button>
                                    <button type="button" class="chip-select-btn active" onclick="toggleOwnerSize(this, 'M')">M</button>
                                    <button type="button" class="chip-select-btn active" onclick="toggleOwnerSize(this, 'L')">L</button>
                                    <button type="button" class="chip-select-btn active" onclick="toggleOwnerSize(this, 'XL')">XL</button>
                                    <button type="button" class="chip-select-btn" onclick="toggleOwnerSize(this, 'XXL')">XXL</button>
                                    <button type="button" class="chip-select-btn" onclick="toggleOwnerSize(this, '3XL')">3XL</button>
                                </div>
                            </div>

                            <!-- Available Colours -->
                            <div class="form-group">
                                <label class="form-label">Colours Available (Click to toggle)</label>
                                <div class="swatch-picker-row" id="ownerColorSwatches">
                                    <div class="color-swatch-item active" onclick="toggleOwnerColor(this, 'black')">
                                        <span class="swatch-circle" style="background: #000;"></span> Obsidian Black
                                    </div>
                                    <div class="color-swatch-item active" onclick="toggleOwnerColor(this, 'red')">
                                        <span class="swatch-circle" style="background: #ff1438;"></span> Crimson Red
                                    </div>
                                    <div class="color-swatch-item" onclick="toggleOwnerColor(this, 'grey')">
                                        <span class="swatch-circle" style="background: #4a4a55;"></span> Charcoal Grey
                                    </div>
                                    <div class="color-swatch-item" onclick="toggleOwnerColor(this, 'white')">
                                        <span class="swatch-circle" style="background: #ffffff;"></span> Bone White
                                    </div>
                                </div>
                            </div>

                            <!-- Upload Images -->
                            <div class="form-group">
                                <label class="form-label">Upload Product Image *</label>
                                <div class="upload-dropzone" id="ownerDropzone" onclick="document.getElementById('ownerImgInput').click()">
                                    <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2rem; color: var(--color-red); margin-bottom: 8px;"></i>
                                    <div style="font-weight: 600; font-size: 0.9rem;">Click or Drag & Drop Image Here</div>
                                    <span style="font-size: 0.75rem; color: var(--color-muted);">PNG, JPG, WEBP accepted (Instant client preview)</span>
                                    <input type="file" id="ownerImgInput" accept="image/*" style="display:none;" onchange="handleOwnerImageFile(this)">
                                </div>
                                
                                <div class="upload-preview-box" id="uploadPreviewBox">
                                    <img src="assets/sig_tee.png" id="uploadPreviewImg" alt="Preview">
                                </div>
                            </div>

                            <!-- Write Description -->
                            <div class="form-group">
                                <label class="form-label">Product Description & Tailoring Notes</label>
                                <textarea id="ownerDescription" class="form-control-custom" rows="3" placeholder="Describe the cut, silhouette, collar ribbing, and streetwear energy..."></textarea>
                            </div>
                        </div>

                    </div>

                    <!-- Submit Actions -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 24px; border-top: 1px solid var(--color-border); padding-top: 20px;">
                        <a href="owner.html" class="btn btn-secondary btn-sm" target="_blank">
                            <i class="fa-solid fa-table-list me-1"></i> Open Full Owner Hub
                        </a>
                        <div style="display:flex; gap: 12px;">
                            <button type="button" class="btn btn-outline" onclick="closeOwnerModal()">Cancel</button>
                            <button type="submit" class="btn btn-red px-4 py-3" style="font-size: 1rem; font-weight:700;">
                                <i class="fa-solid fa-plus-circle me-2"></i> Publish To Store
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(modal);
    }

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    bsAudio.playClick();
}

function closeOwnerModal() {
    const modal = document.getElementById('ownerModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        bsAudio.playClick();
    }
}

function toggleOwnerSize(btn, size) {
    if (ownerSelectedSizes.has(size)) {
        if (ownerSelectedSizes.size > 1) {
            ownerSelectedSizes.delete(size);
            btn.classList.remove('active');
        }
    } else {
        ownerSelectedSizes.add(size);
        btn.classList.add('active');
    }
    bsAudio.playClick();
}

function toggleOwnerColor(item, color) {
    if (ownerSelectedColors.has(color)) {
        if (ownerSelectedColors.size > 1) {
            ownerSelectedColors.delete(color);
            item.classList.remove('active');
        }
    } else {
        ownerSelectedColors.add(color);
        item.classList.add('active');
    }
    bsAudio.playClick();
}

function selectOwnerQuality(chip, qualityText) {
    const chips = chip.parentElement.querySelectorAll('.chip-select-btn');
    chips.forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    const input = document.getElementById('ownerQualityInput');
    if (input) input.value = qualityText;
    bsAudio.playClick();
}

function handleOwnerImageFile(input) {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        ownerUploadedImageSrc = e.target.result;
        const previewBox = document.getElementById('uploadPreviewBox');
        const previewImg = document.getElementById('uploadPreviewImg');
        if (previewBox && previewImg) {
            previewImg.src = ownerUploadedImageSrc;
            previewBox.style.display = 'block';
        }
        bsAudio.playSuccess();
    };
    reader.readAsDataURL(file);
}

function handleOwnerProductSubmit(e) {
    e.preventDefault();

    const title = document.getElementById('ownerTitle').value.trim();
    const category = document.getElementById('ownerCategory').value;
    const badge = document.getElementById('ownerBadge').value.trim() || 'NEW DROP';
    const price = parseFloat(document.getElementById('ownerPrice').value) || 49.00;
    const origPriceVal = document.getElementById('ownerOrigPrice').value;
    const origPrice = origPriceVal ? parseFloat(origPriceVal) : Math.round(price * 1.3);
    const quality = document.getElementById('ownerQualityInput').value.trim();
    const hasSizeChart = document.getElementById('ownerHasSizeChart').checked;
    const description = document.getElementById('ownerDescription').value.trim() || 'Exclusive streetwear release designed with heavy density organic cotton and pre-shrunk anti-twist silhouette.';

    const gsmMatch = quality.match(/\d+\s*GSM/i);
    const gsm = gsmMatch ? gsmMatch[0].toUpperCase() : '260 GSM';

    const newProduct = {
        id: 'owner-item-' + Date.now(),
        name: title,
        category: category,
        price: price,
        originalPrice: origPrice,
        badge: badge,
        rating: 5.0,
        reviews: 1,
        gsm: gsm,
        quality: quality,
        fabric: quality,
        hasSizeChart: hasSizeChart,
        image: ownerUploadedImageSrc || 'assets/sig_tee.png',
        description: description,
        colors: Array.from(ownerSelectedColors),
        sizes: Array.from(ownerSelectedSizes),
        isCustom: true,
        createdAt: new Date().toLocaleDateString()
    };

    saveCustomProduct(newProduct);
    closeOwnerModal();

    bsAudio.playSuccess();
    if (confettiEngine) confettiEngine.burst(80);

    alert(`🎉 Success! "${title}" has been published and is now live on the store!`);
    scrollToSection('shop');
}

// ==========================================
// 9. Products Grid Rendering & Filter
// ==========================================
function renderProducts(filter = 'all') {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;

    const filtered = filter === 'all'
        ? BLACKSTONE_PRODUCTS
        : BLACKSTONE_PRODUCTS.filter(p => p.category.toLowerCase() === filter.toLowerCase());

    grid.innerHTML = filtered.map(p => `
        <div class="product-card" data-id="${p.id}" data-category="${p.category}">
            <div class="product-img-box" onclick="openProductModal('${p.id}')">
                <img src="${p.image}" alt="${p.name}" loading="lazy">
                <span class="product-badge-overlay">${p.badge}</span>
                <div class="product-quick-view">Quick View</div>
            </div>
            <div class="product-card-body">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="product-tag">${p.category.toUpperCase()} &bull; ${p.gsm}</span>
                    ${p.hasSizeChart ? `
                        <span onclick="openSizeChartModal('${p.id}'); event.stopPropagation();" title="View Size Chart" style="font-size:0.75rem; color:var(--color-red); cursor:pointer;">
                            <i class="fa-solid fa-ruler-combined"></i>
                        </span>
                    ` : ''}
                </div>
                <h3 class="product-title" onclick="openProductModal('${p.id}')" style="cursor:pointer;">${p.name}</h3>
                <div style="font-size:0.75rem; color:var(--color-muted); margin-bottom: 6px;">Sizes: ${(p.sizes || []).join(' · ')}</div>
                <div class="product-bottom-row">
                    <div class="product-price">$${p.price.toFixed(2)}</div>
                    <button class="add-cart-btn-icon" title="Add to Bag" onclick="quickAddToCart('${p.id}', event)">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function setupShopFilters() {
    const filterContainer = document.getElementById('shopFilters');
    if (!filterContainer) return;

    const buttons = filterContainer.querySelectorAll('.filter-btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const category = btn.getAttribute('data-filter') || 'all';
            renderProducts(category);
            bsAudio.playClick();
        });
    });
}

function scrollToSection(sectionId) {
    const target = document.getElementById(sectionId);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth' });
        bsAudio.playClick();
    } else {
        window.location.href = `index.html#${sectionId}`;
    }
}

// ==========================================
// 10. Mystery Box & Code Zone State Engine
// ==========================================
const BS_STORAGE_KEY = 'blackstone_mystery_state_v1';

const DEFAULT_DEMO_CODES = {
    'BLACKSTONE-DEMO1': {
        code: 'BLACKSTONE-DEMO1',
        rarity: 'legendary',
        title: 'Legendary Black Stone Chest',
        prize: '₹1,000 Voucher + Signature Hoodie',
        desc: 'Valid on next luxury drop. Unlocked via VIP label verification.'
    },
    'BLACKSTONE-DEMO2': {
        code: 'BLACKSTONE-DEMO2',
        rarity: 'epic',
        title: 'Epic Mystery Drop',
        prize: '30% OFF Entire Cart + Silver Signet Ring',
        desc: 'Applied at checkout for all premium collection drops.'
    },
    'BLACKSTONE-CODE99': {
        code: 'BLACKSTONE-CODE99',
        rarity: 'rare',
        title: 'Rare Streetwear Booster',
        prize: '20% Discount Coupon + Sticker Pack',
        desc: 'Instant store credit code: BSTONE20X'
    }
};

function getMysteryState() {
    try {
        const data = localStorage.getItem(BS_STORAGE_KEY);
        if (data) return JSON.parse(data);
    } catch (e) {
        console.warn('Storage read error', e);
    }

    return {
        collectedTags: 2,
        redeemedCodes: [],
        unopenedBoxes: [
            {
                id: 'demo-box-1',
                title: 'Welcome Mystery Chest',
                rarity: 'epic',
                prize: '30% OFF Exclusive Drop',
                desc: 'Special reward for joining Black Stone community.'
            }
        ],
        openedRewards: []
    };
}

function saveMysteryState(state) {
    try {
        localStorage.setItem(BS_STORAGE_KEY, JSON.stringify(state));
    } catch (e) {
        console.warn('Storage write error', e);
    }
}

// ==========================================
// 11. Code Zone Controller
// ==========================================
function initCodeZonePage() {
    const redeemForm = document.getElementById('codeRedeemForm');
    if (!redeemForm) return;

    renderMilestoneTracker();
    renderInventoryLists();

    const prefill = sessionStorage.getItem('blackstone_prefill_code');
    if (prefill) {
        prefillCode(prefill);
        sessionStorage.removeItem('blackstone_prefill_code');
    }

    redeemForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const codeInput = document.getElementById('mysteryCodeInput');
        if (!codeInput) return;

        const codeVal = codeInput.value.trim().toUpperCase();
        if (!codeVal) {
            showCodeAlert('Please enter a garment label code.', 'error');
            bsAudio.playError();
            return;
        }

        const state = getMysteryState();

        if (state.redeemedCodes.includes(codeVal)) {
            showCodeAlert('This code has already been redeemed in your lounge!', 'error');
            bsAudio.playError();
            return;
        }

        let prizeData = DEFAULT_DEMO_CODES[codeVal];
        if (!prizeData) {
            if (codeVal.startsWith('BLACKSTONE-') || codeVal.startsWith('WELCOME-') || codeVal.length >= 8) {
                const rarities = ['rare', 'epic', 'legendary'];
                const selectedRarity = rarities[Math.floor(Math.random() * rarities.length)];
                prizeData = {
                    code: codeVal,
                    rarity: selectedRarity,
                    title: `${selectedRarity.toUpperCase()} T-Shirt Reward Chest`,
                    prize: selectedRarity === 'legendary' ? '₹1,500 Gift Voucher' : (selectedRarity === 'epic' ? '40% OFF Coupon' : '20% OFF Code'),
                    desc: 'Verified garment tag reward.'
                };
            } else {
                showCodeAlert('Invalid code format. Format: BLACKSTONE-XXXX or click a demo code below.', 'error');
                bsAudio.playError();
                return;
            }
        }

        state.redeemedCodes.push(codeVal);
        state.collectedTags = (state.collectedTags + 1);

        const newBox = {
            id: 'box-' + Date.now(),
            title: prizeData.title,
            rarity: prizeData.rarity,
            prize: prizeData.prize,
            desc: prizeData.desc,
            unlockedVia: codeVal
        };
        state.unopenedBoxes.unshift(newBox);

        let milestoneAwarded = false;
        if (state.collectedTags % 5 === 0) {
            const grandChest = {
                id: 'grand-' + Date.now(),
                title: 'GRAND VAULT MASTER CHEST',
                rarity: 'legendary',
                prize: 'Heavyweight Signature Hoodie + ₹2,000 Voucher',
                desc: 'Unlocked by collecting 5 verified Black Stone T-Shirts!'
            };
            state.unopenedBoxes.unshift(grandChest);
            milestoneAwarded = true;
        }

        saveMysteryState(state);
        codeInput.value = '';

        if (milestoneAwarded) {
            showCodeAlert('🎉 Code redeemed! PLUS you completed 5 T-shirts & unlocked the GRAND VAULT MASTER CHEST!', 'success');
        } else {
            showCodeAlert(`Code verified! Added 1 ${prizeData.rarity.toUpperCase()} Chest to your lounge.`, 'success');
        }

        bsAudio.playUnboxFanfare();
        if (confettiEngine) confettiEngine.burst(50);
        renderMilestoneTracker();
        renderInventoryLists();
    });
}

function prefillCode(code) {
    const input = document.getElementById('mysteryCodeInput');
    if (input) {
        input.value = code;
        input.focus();
        bsAudio.playClick();
    }
}

function showCodeAlert(msg, type) {
    const alertBox = document.getElementById('codeAlertMsg');
    if (!alertBox) return;
    alertBox.textContent = msg;
    alertBox.style.display = 'block';
    alertBox.className = `auth-message show ${type === 'success' ? 'success' : 'error'}`;
    if (type === 'success') {
        alertBox.style.color = 'var(--color-red)';
        alertBox.style.borderColor = 'rgba(255, 20, 56, 0.4)';
    } else {
        alertBox.style.color = '#f87171';
        alertBox.style.borderColor = 'rgba(248, 113, 113, 0.4)';
    }
}

function renderMilestoneTracker() {
    const state = getMysteryState();
    const countEl = document.getElementById('milestoneCount');
    const slotsEl = document.getElementById('milestoneSlots');
    if (!countEl || !slotsEl) return;

    const currentInCycle = (state.collectedTags % 5 === 0 && state.collectedTags > 0) ? 5 : (state.collectedTags % 5);
    countEl.textContent = `${currentInCycle} / 5 TAGS`;

    slotsEl.innerHTML = '';
    for (let i = 1; i <= 5; i++) {
        const isUnlocked = i <= currentInCycle;
        const slot = document.createElement('div');
        slot.className = `milestone-slot ${isUnlocked ? 'unlocked' : ''}`;
        slot.innerHTML = `
            <div class="slot-icon"><i class="fa-solid ${isUnlocked ? 'fa-shirt' : 'fa-lock'}"></i></div>
            <div class="slot-label">Tag #${i}</div>
        `;
        slotsEl.appendChild(slot);
    }
}

function renderInventoryLists() {
    const state = getMysteryState();
    const unopenedContainer = document.getElementById('unopenedChestsGrid');
    const openedContainer = document.getElementById('openedRewardsGrid');
    const unboxCountEl = document.getElementById('unopenedCountBadge');

    if (unboxCountEl) {
        unboxCountEl.textContent = state.unopenedBoxes.length;
    }

    if (unopenedContainer) {
        if (state.unopenedBoxes.length === 0) {
            unopenedContainer.innerHTML = `
                <div class="drop-banner text-center" style="grid-column: 1 / -1; align-items: center; justify-content: center; padding: 40px;">
                    <i class="fa-solid fa-box-open" style="font-size: 3rem; color: var(--color-muted);"></i>
                    <h3 style="margin-top: 10px;">No Unopened Mystery Boxes</h3>
                    <p style="color: var(--color-muted);">Enter a garment code above to unlock a fresh reward chest immediately!</p>
                </div>
            `;
        } else {
            unopenedContainer.innerHTML = '';
            state.unopenedBoxes.forEach(box => {
                const card = document.createElement('div');
                card.className = `chest-card ${box.rarity}`;
                card.innerHTML = `
                    <div class="chest-icon-wrapper">
                        <i class="fa-solid fa-gem"></i>
                    </div>
                    <span class="reward-badge" style="position: static; display: inline-block; margin-bottom: 12px;">${box.rarity.toUpperCase()}</span>
                    <h3 style="font-family: var(--font-heading); margin-bottom: 8px;">${box.title}</h3>
                    <p style="color: var(--color-muted); font-size: 0.9rem; margin-bottom: 24px;">${box.desc}</p>
                    <button class="btn btn-gold w-100 py-3" onclick="triggerUnboxAnimation('${box.id}')">
                        <i class="fa-solid fa-unlock-keyhole me-1"></i> Unbox Chest
                    </button>
                `;
                unopenedContainer.appendChild(card);
            });
        }
    }

    if (openedContainer) {
        if (state.openedRewards.length === 0) {
            openedContainer.innerHTML = `<p style="color: var(--color-muted); font-size: 0.95rem; text-align: center; padding: 30px;">No rewards claimed yet. Open an available box to reveal prizes!</p>`;
        } else {
            openedContainer.innerHTML = '';
            state.openedRewards.forEach(r => {
                const row = document.createElement('div');
                row.className = 'reward-item-card';
                row.style.padding = '20px';
                row.style.textAlign = 'left';
                row.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                        <span style="color: var(--color-red); font-weight:700; font-size:0.8rem; text-transform:uppercase;">CLAIMED REWARD</span>
                        <span style="color: var(--color-muted); font-size:0.8rem;">${r.date || 'Recently'}</span>
                    </div>
                    <h4 style="font-family: var(--font-heading); font-size: 1.1rem; color: #fff;">${r.prize}</h4>
                    <p style="color: var(--color-muted); font-size: 0.85rem; margin-top: 4px;">${r.desc}</p>
                `;
                openedContainer.appendChild(row);
            });
        }
    }
}

function triggerUnboxAnimation(boxId) {
    const state = getMysteryState();
    const boxIndex = state.unopenedBoxes.findIndex(b => b.id === boxId);
    if (boxIndex === -1) return;

    const box = state.unopenedBoxes[boxIndex];

    let modal = document.getElementById('chestModalBackdrop');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'chestModalBackdrop';
        modal.className = 'chest-modal-backdrop';
        modal.innerHTML = `
            <div class="chest-modal-box" id="chestModalBox">
                <div id="chestAnimStage">
                    <div class="chest-icon-wrapper chest-animating" id="modalChestIcon" style="width:130px; height:130px; font-size:4rem; margin-bottom: 24px;">
                        <i class="fa-solid fa-gift"></i>
                    </div>
                    <h2 style="font-family: var(--font-heading); margin-bottom: 10px;" id="modalTitle">Unlocking Mystery Chest...</h2>
                    <p style="color: var(--color-muted);" id="modalSubtitle">Summoning your exclusive Black Stone reward...</p>
                </div>
                <div id="rewardStage" style="display: none;"></div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    const animStage = modal.querySelector('#chestAnimStage');
    const rewardStage = modal.querySelector('#rewardStage');
    const chestIcon = modal.querySelector('#modalChestIcon');

    animStage.style.display = 'block';
    rewardStage.style.display = 'none';
    chestIcon.className = `chest-icon-wrapper chest-animating ${box.rarity}`;

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    bsAudio.playDrumroll(1800);

    setTimeout(() => {
        state.unopenedBoxes.splice(boxIndex, 1);
        state.openedRewards.unshift({
            ...box,
            date: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });
        saveMysteryState(state);

        animStage.style.display = 'none';
        rewardStage.style.display = 'block';
        rewardStage.innerHTML = `
            <span class="reward-badge" style="position: static; margin-bottom: 16px; display:inline-block;">${box.rarity.toUpperCase()} DROP</span>
            <h2 style="font-family: var(--font-heading); color: var(--color-red); font-size: 1.8rem; margin-bottom: 12px;">
                <i class="fa-solid fa-trophy me-2"></i> ${box.prize}
            </h2>
            <p style="color: var(--color-white); font-size: 1.05rem; margin-bottom: 8px;">${box.title}</p>
            <p style="color: var(--color-muted); font-size: 0.9rem; margin-bottom: 28px;">${box.desc}</p>
            <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                <button class="btn btn-gold px-4 py-2" onclick="closeUnboxModal()">
                    <i class="fa-solid fa-check me-1"></i> Claim to Inventory
                </button>
                <a href="index.html#shop" class="btn btn-secondary px-4 py-2" onclick="closeUnboxModal()">
                    <i class="fa-solid fa-bag-shopping me-1"></i> Shop Collection
                </a>
            </div>
        `;

        bsAudio.playUnboxFanfare();
        if (confettiEngine) confettiEngine.burst(100);
        renderMilestoneTracker();
        renderInventoryLists();
    }, 1800);
}

function closeUnboxModal() {
    const modal = document.getElementById('chestModalBackdrop');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        bsAudio.playClick();
    }
}

function initMysteryBoxPage() {
    const state = getMysteryState();
    const countBadge = document.getElementById('mysteryBoxInventoryCount');
    if (countBadge) {
        countBadge.textContent = state.unopenedBoxes.length;
    }
}

// ==========================================
// 12. User Authentication & VIP Session State
// ==========================================
const BS_USER_KEY = 'blackstone_user_v1';

function getUser() {
    try {
        const u = localStorage.getItem(BS_USER_KEY);
        if (u) return JSON.parse(u);
    } catch (e) {
        console.warn('User read error', e);
    }
    return null;
}

function saveUser(user) {
    if (user) {
        localStorage.setItem(BS_USER_KEY, JSON.stringify(user));
    } else {
        localStorage.removeItem(BS_USER_KEY);
    }
    updateUserNavUI();
}

function updateUserNavUI() {
    const user = getUser();
    const btn = document.getElementById('accountActionBtn');
    const text = document.getElementById('accountActionText');
    if (!btn || !text) return;

    if (user && user.isLoggedIn) {
        text.textContent = user.name ? user.name.split(' ')[0] : 'ID Card';
        btn.classList.add('logged-in');
        btn.onclick = () => window.location.href = 'profile.html';
    } else {
        text.textContent = 'Login';
        btn.classList.remove('logged-in');
        btn.onclick = () => window.location.href = 'profile.html';
    }
}

// ==========================================
// 13. Global App Lifecycle & Smoky Nav
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    confettiEngine = new ConfettiEngine();

    // Setup navbar & user state
    updateUserNavUI();

    // Smoky header scroll effect
    const mainHeader = document.querySelector('.main-header');
    if (mainHeader) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 40) {
                mainHeader.classList.add('scrolled');
            } else {
                mainHeader.classList.remove('scrolled');
            }
        });
    }

    // Mobile nav toggle
    const menuToggle = document.getElementById('menuToggle');
    const mainNav = document.getElementById('mainNav');
    if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', () => {
            mainNav.classList.toggle('open');
            bsAudio.playClick();
        });
    }

    // Initialize shop collection if present on page
    if (document.getElementById('productsGrid')) {
        renderProducts('all');
        setupShopFilters();
    }

    // Always render cart state across all pages
    renderCart();

    // Code input formatting in code zone
    const codeInput = document.getElementById('mysteryCodeInput');
    if (codeInput) {
        codeInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.toUpperCase();
        });
    }

    // Page-specific initializers
    initCodeZonePage();
    initMysteryBoxPage();

    // Global keyboard listener (Escape closes modals)
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeProductModal();
            closeUnboxModal();
            closeOwnerModal();
            closeSizeChartModal();
            toggleCart(false);
            const checkoutModal = document.getElementById('checkoutModal');
            if (checkoutModal) checkoutModal.classList.remove('active');
        }
    });
});
