/* ==========================================================================
   BLACK STONE BRAND WEB APPLICATION LOGIC (Multi-page Experience)
   ========================================================================== */

// 1. PRODUCT CATALOG DATA
const PRODUCTS = [
    {
        id: 1,
        name: "Signature Gold Crest Tee",
        price: 49.00,
        type: "signature",
        image: "assets/sig_tee.png",
        badge: "Best Seller",
        fit: "Drop shoulder / 260GSM",
        drop: "Core Icon",
        desc: "A statement piece featuring our signature geometric stone crest printed in premium metallic gold leaf foil. Crafted from 260GSM ultra-heavyweight pre-shrunk organic cotton. Featuring a tailored drop-shoulder fit, thick ribbed collar, and double-needle stitching throughout.",
        sizes: ["S", "M", "L", "XL"],
        colors: ["black", "grey"]
    },
    {
        id: 2,
        name: "Stealth Pattern Tee",
        price: 55.00,
        type: "minimalist",
        image: "assets/stealth_tee.png",
        badge: "New Release",
        fit: "Soft combed cotton",
        drop: "Stealth Line",
        desc: "Understated elegance. Abstract glowing geometric stone lines stretch across the chest in a luxurious golden gradient. Constructed with breathable, extra-soft combed long-staple cotton for daily street comfort. Engineered to preserve shape and print vibrancy through countless washes.",
        sizes: ["S", "M", "L", "XL"],
        colors: ["black", "grey", "white"]
    },
    {
        id: 3,
        name: "Typographic Gold Tee",
        price: 45.00,
        type: "graphic",
        image: "assets/gold_tee.png",
        badge: "Limited Edition",
        fit: "Structured street fit",
        drop: "Gold Type",
        desc: "A clean aesthetic highlighting our brand legacy. The 'BLACK STONE' bold typographic logo sits centered in premium gold flake print on structured heavy white cotton. Provides a crisp, modern street silhouette that transitions effortlessly from day to night.",
        sizes: ["M", "L", "XL"],
        colors: ["white", "black"]
    },
    {
        id: 4,
        name: "Cosmic Swirl Tee",
        price: 52.00,
        type: "graphic",
        image: "assets/nebula_tee.png",
        badge: "Limited Drop",
        fit: "Water-based graphic",
        drop: "Cosmic Capsule",
        desc: "A galactic journey. A stunning high-definition circular nebula print infused with golden cosmic dust particles. This eye-catching graphic is printed using eco-friendly, water-based inks that blend perfectly into the premium 260GSM black cotton weave.",
        sizes: ["S", "M", "L"],
        colors: ["black"]
    }
];

// PRE-DEFINED MOCK CODES (So users can test without purchasing)
const STARTER_CODES = ["BS-WELCME", "BS-MEMBER", "BS-STREET", "BS-MYST-X", "BS-LUCKY7"];

// REWARD POOL FOR THE MYSTERY BOX
const REWARDS = [
    {
        rarity: "LEGENDARY REWARD",
        title: "Exclusive Embroidered Bomber Jacket",
        desc: "A heavy-duty satin street bomber jacket detailed with high-density gold thread embroidery of our Stone Emblem. Complete with custom gold hardware and a silk lining.",
        codePrefix: "BS-BOMBER-"
    },
    {
        rarity: "EPIC REWARD",
        title: "Sterling Silver Signet Ring",
        desc: "A solid .925 sterling silver signet ring, hand-carved with the geometric Black Stone crest. Individually polished and packaged in a velvet pouch.",
        codePrefix: "BS-RING-"
    },
    {
        rarity: "RARE REWARD",
        title: "$150 Black Stone Gift Card",
        desc: "Store credit gift voucher valid across our entire catalog, including future drops and app-exclusive releases.",
        codePrefix: "BS-GIFT-"
    },
    {
        rarity: "COMMON REWARD",
        title: "Street Pack (Beanie + Socks)",
        desc: "A cozy ribbed black knit beanie with gold-stitched logo, paired with heavy-duty athletic crew socks detailing gold stone graphics.",
        codePrefix: "BS-PACK-"
    }
];

// 2. STATE MANAGER
let state = {
    cart: [],
    generatedCodes: new Set(), // Codes created upon checkouts
    usedCodes: new Set(),      // Codes that have been successfully entered
    activeFilter: "all"
};

// Initialize State from Local Storage if exists (to persist experience)
function loadState() {
    const saved = localStorage.getItem("black_stone_state");
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            state.cart = parsed.cart || [];
            state.generatedCodes = new Set(parsed.generatedCodes || []);
            state.usedCodes = new Set(parsed.usedCodes || []);
        } catch (e) {
            console.error("Failed to parse saved state", e);
        }
    }
}

function saveState() {
    const obj = {
        cart: state.cart,
        generatedCodes: Array.from(state.generatedCodes),
        usedCodes: Array.from(state.usedCodes)
    };
    localStorage.setItem("black_stone_state", JSON.stringify(obj));
}

// 3. APPLICATION ONLOAD INITIALIZATION
document.addEventListener("DOMContentLoaded", () => {
    loadState();
    renderProducts();
    updateCartUI();
    updateAuthUI();
    renderEnteredCodesLedger();
    updateProgressTracker();
    setupNavigation();
    setupFAQ();
    setupForms();
    setupAuthPortal();
    setupScrollReveal();
});

// 4. NAVIGATION & MOBILE MENU SETUP
function setupNavigation() {
    const sections = document.querySelectorAll("section");
    const navLinks = document.querySelectorAll(".nav-link");
    const menuToggle = document.getElementById("menuToggle");
    const mainNav = document.getElementById("mainNav");
    const currentPage = window.location.pathname.split("/").pop() || "index.html";

    // Smooth scroll navigation highlight
    window.addEventListener("scroll", () => {
        let current = "";
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (pageYOffset >= (sectionTop - 120)) {
                current = section.getAttribute("id");
            }
        });

        navLinks.forEach(link => {
            const href = link.getAttribute("href") || "";
            const hrefPage = href.split("#")[0] || "index.html";
            link.classList.remove("active");
            if ((href === `#${current}`) || (hrefPage === currentPage && !href.includes("#"))) {
                link.classList.add("active");
            }
        });
    });

    // Mobile menu toggle
    if (menuToggle && mainNav) {
        menuToggle.addEventListener("click", () => {
            mainNav.classList.toggle("active");
            const isOpen = mainNav.classList.contains("active");
            menuToggle.innerHTML = isOpen ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-bars"></i>';
        });

        // Close menu when clicking nav link on mobile
        navLinks.forEach(link => {
            link.addEventListener("click", () => {
                mainNav.classList.remove("active");
                menuToggle.innerHTML = '<i class="fa-solid fa-bars"></i>';
            });
        });
    }
}

function scrollToSection(id) {
    const el = document.getElementById(id);
    if (el) {
        const offset = 80; // header height
        const bodyRect = document.body.getBoundingClientRect().top;
        const elementRect = el.getBoundingClientRect().top;
        const elementPosition = elementRect - bodyRect;
        const offsetPosition = elementPosition - offset;

        window.scrollTo({
            top: offsetPosition,
            behavior: "smooth"
        });
    } else if (id === "shop") {
        window.location.href = "index.html#shop";
    } else if (id === "code-zone") {
        window.location.href = "code-zone.html";
    } else if (id === "app-section") {
        window.location.href = "app.html";
    }
}

// 5. PRODUCT CATALOG RENDERING & FILTERING
function renderProducts() {
    const grid = document.getElementById("productsGrid");
    const filters = document.getElementById("shopFilters");
    
    if (!grid) return;

    // Filter Button Clicks
    if (filters && !filters.dataset.bound) {
        filters.dataset.bound = "true";
        filters.querySelectorAll(".filter-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                filters.querySelectorAll(".filter-btn").forEach(b => b.classList.remove("active"));
                btn.classList.add("active");
                state.activeFilter = btn.dataset.filter;
                renderProducts();
            });
        });
    }

    grid.innerHTML = "";
    
    const filtered = PRODUCTS.filter(p => {
        if (state.activeFilter === "all") return true;
        return p.type === state.activeFilter;
    });

    filtered.forEach(p => {
        const card = document.createElement("div");
        card.className = "product-card";
        card.innerHTML = `
            <div class="product-img-box">
                <img src="${p.image}" alt="${p.name}" loading="lazy">
                ${p.badge ? `<div class="product-badge-overlay">${p.badge}</div>` : ""}
                <div class="product-quick-view" onclick="openProductModal(${p.id})">Quick View</div>
            </div>
            <div class="product-card-body">
                <div class="product-meta-row">
                    <div class="product-tag">${p.drop}</div>
                    <div class="product-fit">${p.fit}</div>
                </div>
                <h3 class="product-title">${p.name}</h3>
                <div class="product-card-desc">${p.desc.split(".")[0]}.</div>
                <div class="product-bottom-row">
                    <span class="product-price">$${p.price.toFixed(2)}</span>
                    <button class="add-cart-btn-icon" onclick="openProductModal(${p.id})" title="Select options & buy">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}

// 6. PRODUCT DETAIL MODAL ACTIONS
function openProductModal(productId) {
    const modal = document.getElementById("productModal");
    const grid = document.getElementById("productModalGrid");
    const product = PRODUCTS.find(p => p.id === productId);

    if (!modal || !grid || !product) return;

    // Default choices
    let selectedSize = product.sizes[0] || "M";
    let selectedColor = product.colors[0] || "black";

    grid.innerHTML = `
        <div class="product-modal-gallery">
            <img src="${product.image}" alt="${product.name}">
        </div>
        <div class="product-modal-info">
            <span class="modal-product-tag">${product.type}</span>
            <h2 class="modal-product-title">${product.name}</h2>
            <div class="modal-product-price">$${product.price.toFixed(2)}</div>
            <p class="modal-product-desc">${product.desc}</p>
            <div class="modal-product-highlights">
                <span><i class="fa-solid fa-shirt"></i> ${product.fit}</span>
                <span><i class="fa-solid fa-tag"></i> ${product.drop}</span>
                <span><i class="fa-solid fa-ticket"></i> Mystery code included</span>
            </div>
            
            <div class="option-group">
                <div class="option-label">Select Size</div>
                <div class="size-chips">
                    ${product.sizes.map(s => `
                        <div class="size-chip ${s === selectedSize ? 'active' : ''}" data-size="${s}">${s}</div>
                    `).join("")}
                </div>
            </div>

            <div class="option-group">
                <div class="option-label">Select Color</div>
                <div class="color-dots">
                    ${product.colors.map(c => `
                        <div class="color-dot ${c} ${c === selectedColor ? 'active' : ''}" data-color="${c}" title="${c}"></div>
                    `).join("")}
                </div>
            </div>

            <div class="modal-product-ctas">
                <button class="btn btn-gold w-100" id="addToCartBtn">
                    <i class="fa-solid fa-cart-plus"></i> Add To Bag
                </button>
            </div>
        </div>
    `;

    // Size chip selection listener
    const sizeChips = grid.querySelectorAll(".size-chip");
    sizeChips.forEach(chip => {
        chip.addEventListener("click", () => {
            sizeChips.forEach(c => c.classList.remove("active"));
            chip.classList.add("active");
            selectedSize = chip.dataset.size;
        });
    });

    // Color dot selection listener
    const colorDots = grid.querySelectorAll(".color-dot");
    colorDots.forEach(dot => {
        dot.addEventListener("click", () => {
            colorDots.forEach(d => d.classList.remove("active"));
            dot.classList.add("active");
            selectedColor = dot.dataset.color;
        });
    });

    // Add to Cart Button Action
    grid.querySelector("#addToCartBtn").addEventListener("click", () => {
        addToCart(product, selectedSize, selectedColor);
        closeProductModal();
        toggleCart(true); // Open cart drawer to see item
    });

    modal.classList.add("active");
}

function closeProductModal() {
    const modal = document.getElementById("productModal");
    if (modal) modal.classList.remove("active");
}

// 7. SHOPPING CART OPERATIONS
function addToCart(product, size, color) {
    // Check if duplicate item exists
    const existing = state.cart.find(item => item.product.id === product.id && item.size === size && item.color === color);
    
    if (existing) {
        existing.quantity += 1;
    } else {
        state.cart.push({
            product: product,
            size: size,
            color: color,
            quantity: 1
        });
    }

    saveState();
    updateCartUI();
}

function removeFromCart(index) {
    state.cart.splice(index, 1);
    saveState();
    updateCartUI();
}

function updateQuantity(index, change) {
    state.cart[index].quantity += change;
    if (state.cart[index].quantity <= 0) {
        state.cart.splice(index, 1);
    }
    saveState();
    updateCartUI();
}

function toggleCart(show) {
    const drawer = document.getElementById("cartDrawer");
    if (!drawer) return;
    if (show) {
        drawer.classList.add("active");
    } else {
        drawer.classList.remove("active");
    }
}

function updateCartUI() {
    const countBadge = document.getElementById("cartCount");
    const drawerCount = document.getElementById("cartDrawerCount");
    const itemsList = document.getElementById("cartItemsList");
    const footer = document.getElementById("cartFooter");
    const subtotalText = document.getElementById("cartTotalVal");

    const totalQty = state.cart.reduce((sum, item) => sum + item.quantity, 0);
    const subtotal = state.cart.reduce((sum, item) => sum + (item.product.price * item.quantity), 0);

    if (countBadge) countBadge.textContent = totalQty;
    if (drawerCount) drawerCount.textContent = totalQty;

    if (!itemsList) return;

    if (state.cart.length === 0) {
        itemsList.innerHTML = `
            <div class="empty-cart-message">
                <i class="fa-solid fa-bag-shopping"></i>
                <p>Your shopping bag is empty.</p>
                <button class="btn btn-gold" onclick="toggleCart(false); scrollToSection('shop')">Shop T-Shirts</button>
            </div>
        `;
        if (footer) footer.style.display = "none";
    } else {
        itemsList.innerHTML = "";
        state.cart.forEach((item, index) => {
            const div = document.createElement("div");
            div.className = "cart-item";
            div.innerHTML = `
                <div class="cart-item-img-box">
                    <img src="${item.product.image}" alt="${item.product.name}">
                </div>
                <div class="cart-item-details">
                    <div>
                        <div class="cart-item-title">${item.product.name}</div>
                        <div class="cart-item-option">Size: ${item.size} | Color: ${item.color}</div>
                    </div>
                    <div class="cart-item-bottom">
                        <div class="qty-selector">
                            <button class="qty-btn" onclick="updateQuantity(${index}, -1)">-</button>
                            <span class="qty-num">${item.quantity}</span>
                            <button class="qty-btn" onclick="updateQuantity(${index}, 1)">+</button>
                        </div>
                        <span class="cart-item-price">$${(item.product.price * item.quantity).toFixed(2)}</span>
                        <span class="cart-item-remove" onclick="removeFromCart(${index})"><i class="fa-solid fa-trash-can"></i></span>
                    </div>
                </div>
            `;
            itemsList.appendChild(div);
        });
        
        if (subtotalText) subtotalText.textContent = `$${subtotal.toFixed(2)}`;
        if (footer) footer.style.display = "block";
    }
}

// 8. SIMULATED CHECKOUT & CODE TICKETS GENERATOR
function checkoutCart() {
    if (state.cart.length === 0) return;

    // Generate 5 unique secret codes for code zone simulation.
    // In real life, it would be 1 code per shirt, but let's always give them 5 codes
    // if their cart value is premium, or let's generate exactly the number of codes corresponding to items!
    // Since we need 5 codes to unlock the box, if they buy items we will generate codes.
    // Let's generate exactly 5 codes to let them unlock the box immediately and experience the peak wow factor!
    const orderId = "BS-" + Math.floor(100000 + Math.random() * 900000);
    const codesToGenerate = [];
    
    // Always generate 5 codes on checkout so they get 5 codes instantly!
    for (let i = 0; i < 5; i++) {
        // Form: BS-XXXXXX where X is alphanumeric
        const code = "BS-" + Math.random().toString(36).substring(2, 8).toUpperCase();
        codesToGenerate.push(code);
        state.generatedCodes.add(code);
    }

    saveState();

    // Populate checkout modal
    const orderEl = document.getElementById("checkoutOrderId");
    if (orderEl) orderEl.textContent = "#" + orderId;
    const codesContainer = document.getElementById("generatedCodesList");
    if (!codesContainer) return;
    codesContainer.innerHTML = "";
    
    codesToGenerate.forEach(code => {
        const ticket = document.createElement("div");
        ticket.className = "generated-code-ticket";
        ticket.textContent = code;
        codesContainer.appendChild(ticket);
    });

    // Reset Cart
    state.cart = [];
    saveState();
    updateCartUI();
    toggleCart(false);

    // Open Success Modal
    const modal = document.getElementById("checkoutModal");
    if (modal) modal.classList.add("active");
}

function closeCheckoutModalAndGoToCodes() {
    const modal = document.getElementById("checkoutModal");
    if (modal) modal.classList.remove("active");
    window.location.href = "code-zone.html";
}

function copyAllGeneratedCodes() {
    const tickets = document.querySelectorAll(".generated-code-ticket");
    const textArray = [];
    tickets.forEach(t => textArray.push(t.textContent));
    
    // Copy as comma-separated values or single codes
    navigator.clipboard.writeText(textArray.join(", ")).then(() => {
        alert("Codes copied to clipboard! You can paste them one by one in the Code Zone.");
    }).catch(err => {
        console.error("Clipboard copy failed", err);
    });
}

// 9. CODE ZONE VERIFICATION & TRACKING
function renderEnteredCodesLedger() {
    const ledger = document.getElementById("enteredCodesLedger");
    if (!ledger) return;

    if (state.usedCodes.size === 0) {
        ledger.innerHTML = `<li class="ledger-empty">No codes submitted yet.</li>`;
    } else {
        ledger.innerHTML = "";
        state.usedCodes.forEach(code => {
            const li = document.createElement("li");
            li.className = "ledger-item";
            li.innerHTML = `<i class="fa-solid fa-circle-check"></i> <span>${code}</span>`;
            ledger.appendChild(li);
        });
    }
}

function updateProgressTracker() {
    const countVal = state.usedCodes.size;
    const progressPercent = Math.min(countVal * 20, 100);
    const fill = document.getElementById("progressBarFill");
    const percentText = document.getElementById("trackerPercent");
    const countText = document.getElementById("enteredCodesCount");
    const lockText = document.getElementById("lockIndicator");
    const unlockOverlay = document.getElementById("mysteryUnlockOverlay");
    const remainingText = document.getElementById("remainingCountText");

    if (fill) fill.style.width = `${progressPercent}%`;
    if (percentText) percentText.textContent = `${progressPercent}%`;
    if (countText) countText.textContent = countVal;
    
    if (remainingText) {
        remainingText.textContent = 5 - countVal;
    }

    // Toggle active milestones
    for (let i = 1; i <= 5; i++) {
        const dot = document.getElementById(`milestone-${i}`);
        if (dot) {
            dot.className = "milestone-dot"; // reset
            if (countVal >= i) {
                dot.classList.add("active");
            }
        }
    }

    // Lock/Unlock Box States
    if (countVal >= 5) {
        if (lockText) lockText.style.display = "none";
        if (unlockOverlay) unlockOverlay.style.display = "flex";
    } else {
        if (lockText) lockText.style.display = "flex";
        if (unlockOverlay) unlockOverlay.style.display = "none";
    }
}

// Handle code submission form
const codeEntryForm = document.getElementById("codeEntryForm");
if (codeEntryForm) {
    codeEntryForm.addEventListener("submit", (e) => {
        e.preventDefault();
        const input = document.getElementById("codeField");
        const errBox = document.getElementById("codeErrorBox");
        
        if (!input || !errBox) return;
        
        const code = input.value.trim().toUpperCase();

        // Reset error box states
        errBox.className = "error-msg-box";
        errBox.style.display = "none";

        // Validations
        if (code.length < 6) {
            showCodeError("Code must be at least 6 characters.", "error");
            return;
        }

        if (state.usedCodes.has(code)) {
            showCodeError("This code has already been registered.", "error");
            return;
        }

        if (state.usedCodes.size >= 5) {
            showCodeError("You have already entered 5 codes! Open your Mystery Box now.", "success");
            return;
        }

        // Check validity
        const isStarter = STARTER_CODES.includes(code);
        const isGenerated = state.generatedCodes.has(code);

        if (isStarter || isGenerated) {
            // SUCCESS!
            state.usedCodes.add(code);
            saveState();
            input.value = "";
            
            showCodeError("Code verified successfully!", "success");
            renderEnteredCodesLedger();
            updateProgressTracker();
        } else {
            // INVALID
            showCodeError("Invalid code. Please check spelling or check out items to generate valid codes.", "error");
        }
    });
}

function showCodeError(message, type) {
    const errBox = document.getElementById("codeErrorBox");
    if (!errBox) return;
    errBox.textContent = message;
    errBox.classList.add("show", type);
}

// 10. MYSTERY BOX REVEAL CELEBRATION (CANVAS PARTICLES & ANIMATIONS)
let canvas, ctx, particles = [];

class Particle {
    constructor(x, y) {
        this.x = x;
        this.y = y;
        this.size = Math.random() * 5 + 3;
        this.speedX = Math.random() * 8 - 4;
        this.speedY = Math.random() * -10 - 2;
        // HSL Golden tones
        this.color = `hsl(${Math.random() * 20 + 40}, 100%, ${Math.random() * 30 + 50}%)`;
        this.gravity = 0.25;
        this.life = 1;
        this.decay = Math.random() * 0.02 + 0.01;
    }

    update() {
        this.x += this.speedX;
        this.speedY += this.gravity;
        this.y += this.speedY;
        this.life -= this.decay;
    }

    draw() {
        ctx.fillStyle = this.color;
        ctx.shadowBlur = this.size * 2;
        ctx.shadowColor = this.color;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.fill();
        ctx.shadowBlur = 0; // reset
    }
}

function initParticles(x, y) {
    canvas = document.getElementById("particleCanvas");
    if (!canvas) return;
    ctx = canvas.getContext("2d");
    resizeCanvas();
    
    particles = [];
    for (let i = 0; i < 150; i++) {
        particles.push(new Particle(x, y));
    }
    
    animateParticles();
}

function resizeCanvas() {
    if (!canvas) return;
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
}

function animateParticles() {
    if (particles.length === 0) return;
    
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    particles.forEach((p, index) => {
        p.update();
        p.draw();
        if (p.life <= 0) {
            particles.splice(index, 1);
        }
    });

    requestAnimationFrame(animateParticles);
}

function triggerMysteryBoxReveal() {
    const overlay = document.getElementById("mysteryRevealOverlay");
    const shakingImg = document.getElementById("shakingBoxImg");
    const burstLight = document.querySelector(".reveal-burst-light");
    const rewardCard = document.getElementById("mysteryRewardCard");

    if (!overlay) return;

    // Open overlay screen
    overlay.classList.add("active");
    shakingImg.className = "reveal-box-img shaking";
    burstLight.className = "reveal-burst-light";
    rewardCard.className = "mystery-reward-card";

    // Play Shaking for 2.5 seconds
    setTimeout(() => {
        // Zoom out the box
        shakingImg.className = "reveal-box-img zoomOut";
        
        // Trigger burst light
        burstLight.classList.add("active");
        
        // Calculate center for particles
        const x = window.innerWidth / 2;
        const y = window.innerHeight / 2;
        initParticles(x, y);

        // Slide in Reward Card
        setTimeout(() => {
            burstLight.className = "reveal-burst-light"; // reset opacity
            
            // Choose a random reward from pool
            const selectedReward = REWARDS[Math.floor(Math.random() * REWARDS.length)];
            const claimCode = selectedReward.codePrefix + Math.random().toString(36).substring(2, 7).toUpperCase();

            document.getElementById("rewardRarity").textContent = selectedReward.rarity;
            document.getElementById("rewardTitle").textContent = selectedReward.title;
            document.getElementById("rewardDesc").textContent = selectedReward.desc;
            document.getElementById("rewardClaimCode").textContent = claimCode;

            rewardCard.classList.add("active");
        }, 1000);

    }, 2500);
}

function copyRewardClaimCode() {
    const text = document.getElementById("rewardClaimCode")?.textContent;
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        alert("Mystery Box claim code copied! Use it at checkout to claim your gift!");
    });
}

function closeMysteryReveal() {
    const overlay = document.getElementById("mysteryRevealOverlay");
    if (overlay) overlay.classList.remove("active");

    // Reset Progress to 0 to let them play again
    state.usedCodes.clear();
    saveState();
    renderEnteredCodesLedger();
    updateProgressTracker();
}

// 11. FAQ ACCORDION CODE
function setupFAQ() {
    const faqItems = document.querySelectorAll(".faq-item");
    faqItems.forEach(item => {
        const question = item.querySelector(".faq-question");
        if (question) {
            question.addEventListener("click", () => {
                const isActive = item.classList.contains("active");
                faqItems.forEach(i => i.classList.remove("active"));
                if (!isActive) {
                    item.classList.add("active");
                }
            });
        }
    });
}

// 12. MOCK CONTACT FORM
function setupForms() {
    const form = document.getElementById("contactForm");
    const success = document.getElementById("contactSuccess");

    if (form) {
        form.addEventListener("submit", (e) => {
            e.preventDefault();
            form.style.display = "none";
            if (success) success.style.display = "flex";
            
            setTimeout(() => {
                form.reset();
                form.style.display = "flex";
                if (success) success.style.display = "none";
            }, 5000);
        });
    }
}

// 13. LOGIN / SIGN UP OTP PORTAL
function getAccounts() {
    try {
        return JSON.parse(localStorage.getItem("black_stone_accounts")) || [];
    } catch (e) {
        return [];
    }
}

function saveAccounts(accounts) {
    localStorage.setItem("black_stone_accounts", JSON.stringify(accounts));
}

function getSession() {
    try {
        return JSON.parse(localStorage.getItem("black_stone_session"));
    } catch (e) {
        return null;
    }
}

function setSession(account) {
    localStorage.setItem("black_stone_session", JSON.stringify({
        name: account.name,
        email: account.email,
        phone: account.phone,
        signedInAt: new Date().toISOString()
    }));
}

function normalizePhone(phone) {
    return phone.trim().replace(/[\s()-]/g, "");
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidPhone(phone) {
    return /^\+?\d{10,15}$/.test(phone);
}

function createOtp() {
    return String(Math.floor(100000 + Math.random() * 900000));
}

function showAuthMessage(message, type = "info") {
    const box = document.getElementById("authMessage");
    if (!box) return;
    box.textContent = message;
    box.className = `auth-message show ${type}`;
}

function clearAuthMessage() {
    const box = document.getElementById("authMessage");
    if (!box) return;
    box.textContent = "";
    box.className = "auth-message";
}

function setAuthMode(mode) {
    const form = document.getElementById("authForm");
    const title = document.getElementById("authFormTitle");
    const sub = document.getElementById("authFormSubtitle");
    const submitText = document.getElementById("authSubmitText");
    const nameGroup = document.getElementById("authNameGroup");
    const modeInput = document.getElementById("authMode");
    const tabs = document.querySelectorAll(".auth-tab");

    if (!form || !modeInput) return;

    modeInput.value = mode;
    form.reset();
    clearAuthMessage();
    document.getElementById("otpPanel")?.classList.remove("active");
    document.getElementById("authFormPanel")?.classList.add("active");

    tabs.forEach(tab => {
        tab.classList.toggle("active", tab.dataset.authMode === mode);
    });

    if (mode === "signup") {
        if (title) title.textContent = "Create your account";
        if (sub) sub.textContent = "Use your email and phone number. We will verify both with a one-time code.";
        if (submitText) submitText.textContent = "Send Sign Up OTP";
        if (nameGroup) nameGroup.style.display = "block";
    } else {
        if (title) title.textContent = "Log in to your account";
        if (sub) sub.textContent = "Enter your registered email and phone number to receive a login OTP.";
        if (submitText) submitText.textContent = "Send Login OTP";
        if (nameGroup) nameGroup.style.display = "none";
    }
}

function startOtpFlow(payload) {
    const otp = createOtp();
    const pending = {
        ...payload,
        otp,
        expiresAt: Date.now() + 5 * 60 * 1000
    };

    localStorage.setItem("black_stone_pending_otp", JSON.stringify(pending));
    document.getElementById("authFormPanel")?.classList.remove("active");
    document.getElementById("otpPanel")?.classList.add("active");

    const target = document.getElementById("otpTargetText");
    const demo = document.getElementById("otpDemoCode");
    const input = document.getElementById("otpField");
    if (target) target.textContent = `${payload.email} and ${payload.phone}`;
    if (demo) demo.textContent = otp;
    if (input) input.value = "";

    showAuthMessage("OTP sent. Use the demo code shown below to verify.", "success");
}

function handleAuthSubmit(e) {
    e.preventDefault();

    const mode = document.getElementById("authMode")?.value || "login";
    const name = document.getElementById("authName")?.value.trim() || "";
    const email = document.getElementById("authEmail")?.value.trim().toLowerCase() || "";
    const phone = normalizePhone(document.getElementById("authPhone")?.value || "");
    const accounts = getAccounts();

    if (mode === "signup" && name.length < 2) {
        showAuthMessage("Please enter your full name.", "error");
        return;
    }

    if (!isValidEmail(email)) {
        showAuthMessage("Please enter a valid email address.", "error");
        return;
    }

    if (!isValidPhone(phone)) {
        showAuthMessage("Please enter a valid phone number with 10 to 15 digits.", "error");
        return;
    }

    const existing = accounts.find(account => account.email === email || account.phone === phone);

    if (mode === "signup" && existing) {
        showAuthMessage("An account with this email or phone already exists. Try logging in.", "error");
        return;
    }

    if (mode === "login") {
        const account = accounts.find(item => item.email === email && item.phone === phone);
        if (!account) {
            showAuthMessage("No verified account matches this email and phone number. Please sign up first.", "error");
            return;
        }
        startOtpFlow({ mode, name: account.name, email, phone });
        return;
    }

    startOtpFlow({ mode, name, email, phone });
}

function verifyOtp() {
    const entered = document.getElementById("otpField")?.value.trim();
    const raw = localStorage.getItem("black_stone_pending_otp");
    if (!raw) {
        showAuthMessage("No OTP request found. Please request a new OTP.", "error");
        return;
    }

    const pending = JSON.parse(raw);
    if (Date.now() > pending.expiresAt) {
        localStorage.removeItem("black_stone_pending_otp");
        showAuthMessage("OTP expired. Please request a new one.", "error");
        return;
    }

    if (entered !== pending.otp) {
        showAuthMessage("Incorrect OTP. Please check the demo code and try again.", "error");
        return;
    }

    const accounts = getAccounts();
    let account = accounts.find(item => item.email === pending.email && item.phone === pending.phone);

    if (!account) {
        account = {
            name: pending.name,
            email: pending.email,
            phone: pending.phone,
            verified: true,
            createdAt: new Date().toISOString()
        };
        accounts.push(account);
        saveAccounts(accounts);
    }

    setSession(account);
    localStorage.removeItem("black_stone_pending_otp");
    showAuthMessage("Verified successfully. You are now signed in.", "success");
    updateAuthUI();
    renderAuthDashboard();
}

function resendOtp() {
    const raw = localStorage.getItem("black_stone_pending_otp");
    if (!raw) {
        showAuthMessage("Start with your email and phone number first.", "error");
        return;
    }

    const pending = JSON.parse(raw);
    startOtpFlow({
        mode: pending.mode,
        name: pending.name,
        email: pending.email,
        phone: pending.phone
    });
}

function logoutAccount() {
    localStorage.removeItem("black_stone_session");
    updateAuthUI();
    renderAuthDashboard();
    showAuthMessage("You have been logged out.", "success");
}

function updateAuthUI() {
    const session = getSession();
    const textNodes = document.querySelectorAll("#accountActionText");
    const buttons = document.querySelectorAll("#accountActionBtn");

    textNodes.forEach(node => {
        node.textContent = session ? "Account" : "Login";
    });

    buttons.forEach(button => {
        button.title = session ? `Signed in as ${session.name}` : "Login or sign up";
    });
}

function renderAuthDashboard() {
    const dashboard = document.getElementById("authDashboard");
    const portal = document.getElementById("authPortal");
    const session = getSession();

    if (!dashboard || !portal) return;

    if (!session) {
        dashboard.style.display = "none";
        portal.style.display = "block";
        return;
    }

    portal.style.display = "none";
    dashboard.style.display = "block";
    dashboard.innerHTML = `
        <div class="auth-dashboard-card glass-panel">
            <span class="section-tag">Verified Member</span>
            <h2>Welcome, ${session.name}</h2>
            <div class="auth-profile-grid">
                <div><span>Email</span><strong>${session.email}</strong></div>
                <div><span>Phone</span><strong>${session.phone}</strong></div>
                <div><span>Status</span><strong>OTP Verified</strong></div>
            </div>
            <div class="auth-actions-row">
                <button class="btn btn-gold" onclick="window.location.href='index.html#shop'">
                    <i class="fa-solid fa-shirt"></i> Shop Collection
                </button>
                <button class="btn btn-outline" onclick="logoutAccount()">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </button>
            </div>
        </div>
    `;
}

function setupAuthPortal() {
    const form = document.getElementById("authForm");
    if (!form) {
        updateAuthUI();
        return;
    }

    document.querySelectorAll(".auth-tab").forEach(tab => {
        tab.addEventListener("click", () => setAuthMode(tab.dataset.authMode));
    });

    form.addEventListener("submit", handleAuthSubmit);
    document.getElementById("otpVerifyBtn")?.addEventListener("click", verifyOtp);
    document.getElementById("otpResendBtn")?.addEventListener("click", resendOtp);
    document.getElementById("otpBackBtn")?.addEventListener("click", () => {
        document.getElementById("otpPanel")?.classList.remove("active");
        document.getElementById("authFormPanel")?.classList.add("active");
        clearAuthMessage();
    });

    setAuthMode("login");
    renderAuthDashboard();
}

// 14. PREMIUM REVEAL MOTION
function setupScrollReveal() {
    const revealTargets = document.querySelectorAll(".step-card, .product-card, .reward-item-card, .glass-panel, .about-image-card, .app-visual, .app-text-content, .auth-panel");

    if (!("IntersectionObserver" in window)) {
        revealTargets.forEach(el => el.classList.add("is-visible"));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.14 });

    revealTargets.forEach(el => {
        el.classList.add("reveal-on-scroll");
        observer.observe(el);
    });
}
