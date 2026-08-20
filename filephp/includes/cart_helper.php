<?php
/**
 * SecureShop - Shopping Cart Helper
 *
 * Cart items are stored in the PHP session for guests
 * and persisted to the cart_items DB table for logged-in users.
 * On login, guest cart is merged into the user's DB cart.
 */

if (!defined('ECOMMERCE_APP')) {
    http_response_code(403); exit('Forbidden');
}

// ===== Cart Session Key =====

/**
 * Get the unique session key for this cart (guest or user).
 */
function cart_session_key(): string {
    if (empty($_SESSION['cart_session_key'])) {
        $_SESSION['cart_session_key'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['cart_session_key'];
}

// ===== Read Cart =====

/**
 * Get all cart items with product details.
 *
 * @return array  Array of cart item rows with product info
 */
function cart_get_items(): array {
    if (is_logged_in()) {
        return db_fetch_all(
            "SELECT ci.id, ci.product_id, ci.quantity,
                    p.name, p.price, p.sale_price, p.stock_quantity, p.image_path, p.slug, p.is_active,
                    (SELECT COUNT(*) FROM products WHERE id=ci.product_id AND is_active=1) AS is_available
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             WHERE ci.user_id = ?
             ORDER BY ci.updated_at DESC",
            'i', [current_user_id()]
        );
    } else {
        $key = cart_session_key();
        return db_fetch_all(
            "SELECT ci.id, ci.product_id, ci.quantity,
                    p.name, p.price, p.sale_price, p.stock_quantity, p.image_path, p.slug, p.is_active,
                    1 AS is_available
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             WHERE ci.session_key = ?
             ORDER BY ci.updated_at DESC",
            's', [$key]
        );
    }
}

/**
 * Get total number of items in cart (sum of quantities).
 */
function cart_count(): int {
    if (is_logged_in()) {
        return (int)db_fetch_value(
            "SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE user_id=?",
            'i', [current_user_id()]
        );
    } else {
        $key = cart_session_key();
        return (int)db_fetch_value(
            "SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE session_key=?",
            's', [$key]
        );
    }
}

/**
 * Calculate the cart subtotal.
 */
function cart_subtotal(array $items): float {
    $total = 0.0;
    foreach ($items as $item) {
        $price  = (float)($item['sale_price'] ?: $item['price']);
        $total += $price * (int)$item['quantity'];
    }
    return round($total, 2);
}

// ===== Modify Cart =====

/**
 * Add a product to the cart (or increment quantity if already in cart).
 *
 * @param int $product_id
 * @param int $quantity
 * @return array ['ok' => bool, 'message' => string]
 */
function cart_add(int $product_id, int $quantity = 1): array {
    if ($quantity < 1 || $quantity > 999) {
        return ['ok' => false, 'message' => 'Invalid quantity.'];
    }

    // Validate product exists and is active
    $product = db_fetch_one(
        "SELECT id, name, stock_quantity, is_active FROM products WHERE id=? LIMIT 1",
        'i', [$product_id]
    );

    if (!$product || !$product['is_active']) {
        return ['ok' => false, 'message' => 'Product not available.'];
    }

    if ($product['stock_quantity'] < $quantity) {
        return ['ok' => false, 'message' => 'Insufficient stock. Only ' . $product['stock_quantity'] . ' available.'];
    }

    if (is_logged_in()) {
        $uid = current_user_id();
        $existing = db_fetch_one(
            "SELECT id, quantity FROM cart_items WHERE user_id=? AND product_id=? LIMIT 1",
            'ii', [$uid, $product_id]
        );
        if ($existing) {
            $new_qty = min((int)$existing['quantity'] + $quantity, $product['stock_quantity']);
            db_execute("UPDATE cart_items SET quantity=?, updated_at=NOW() WHERE id=?", 'ii', [$new_qty, $existing['id']]);
        } else {
            db_execute(
                "INSERT INTO cart_items (user_id,product_id,quantity) VALUES (?,?,?)",
                'iii', [$uid, $product_id, $quantity]
            );
        }
    } else {
        $key = cart_session_key();
        $existing = db_fetch_one(
            "SELECT id, quantity FROM cart_items WHERE session_key=? AND product_id=? LIMIT 1",
            'si', [$key, $product_id]
        );
        if ($existing) {
            $new_qty = min((int)$existing['quantity'] + $quantity, $product['stock_quantity']);
            db_execute("UPDATE cart_items SET quantity=?, updated_at=NOW() WHERE id=?", 'ii', [$new_qty, $existing['id']]);
        } else {
            db_execute(
                "INSERT INTO cart_items (session_key,product_id,quantity) VALUES (?,?,?)",
                'sii', [$key, $product_id, $quantity]
            );
        }
    }

    return ['ok' => true, 'message' => '"' . $product['name'] . '" added to cart.'];
}

/**
 * Update the quantity of a specific cart item.
 *
 * @param int $cart_item_id
 * @param int $quantity
 * @return array ['ok' => bool, 'message' => string]
 */
function cart_update(int $cart_item_id, int $quantity): array {
    if ($quantity < 1 || $quantity > 999) {
        return ['ok' => false, 'message' => 'Invalid quantity.'];
    }

    $item = _get_cart_item($cart_item_id);
    if (!$item) return ['ok' => false, 'message' => 'Cart item not found.'];

    // Check stock
    $stock = (int)db_fetch_value("SELECT stock_quantity FROM products WHERE id=?", 'i', [$item['product_id']]);
    if ($quantity > $stock) {
        return ['ok' => false, 'message' => 'Only ' . $stock . ' in stock.'];
    }

    db_execute("UPDATE cart_items SET quantity=?, updated_at=NOW() WHERE id=?", 'ii', [$quantity, $cart_item_id]);
    return ['ok' => true, 'message' => 'Cart updated.'];
}

/**
 * Remove a specific cart item.
 */
function cart_remove(int $cart_item_id): array {
    $item = _get_cart_item($cart_item_id);
    if (!$item) return ['ok' => false, 'message' => 'Cart item not found.'];

    db_execute("DELETE FROM cart_items WHERE id=?", 'i', [$cart_item_id]);
    return ['ok' => true, 'message' => 'Item removed from cart.'];
}

function cart_update_quantity(int $cart_item_id, int $quantity): array {
    return cart_update($cart_item_id, $quantity);
}

function cart_remove_item(int $cart_item_id): array {
    return cart_remove($cart_item_id);
}

/**
 * Clear all items from the cart.
 */
function cart_clear(): void {
    if (is_logged_in()) {
        db_execute("DELETE FROM cart_items WHERE user_id=?", 'i', [current_user_id()]);
    } else {
        $key = cart_session_key();
        db_execute("DELETE FROM cart_items WHERE session_key=?", 's', [$key]);
    }
}

/**
 * Merge guest cart into user cart on login.
 */
function cart_merge_on_login(int $user_id): void {
    $key = cart_session_key();
    if (!$key) return;

    $guest_items = db_fetch_all(
        "SELECT product_id, quantity FROM cart_items WHERE session_key=?", 's', [$key]
    );

    foreach ($guest_items as $item) {
        $existing = db_fetch_one(
            "SELECT id, quantity FROM cart_items WHERE user_id=? AND product_id=?",
            'ii', [$user_id, $item['product_id']]
        );
        if ($existing) {
            $new_qty = (int)$existing['quantity'] + (int)$item['quantity'];
            db_execute("UPDATE cart_items SET quantity=? WHERE id=?", 'ii', [$new_qty, $existing['id']]);
        } else {
            db_execute(
                "INSERT INTO cart_items (user_id,product_id,quantity) VALUES (?,?,?)",
                'iii', [$user_id, $item['product_id'], $item['quantity']]
            );
        }
    }

    // Clear guest cart
    db_execute("DELETE FROM cart_items WHERE session_key=?", 's', [$key]);
    unset($_SESSION['cart_session_key']);
}

// ===== Internal Helpers =====

/**
 * Get a cart item only if it belongs to the current user/session.
 * (Prevents IDOR: modifying other users' cart items.)
 */
function _get_cart_item(int $cart_item_id): ?array {
    if (is_logged_in()) {
        return db_fetch_one(
            "SELECT id, product_id, quantity FROM cart_items WHERE id=? AND user_id=? LIMIT 1",
            'ii', [$cart_item_id, current_user_id()]
        );
    } else {
        $key = cart_session_key();
        return db_fetch_one(
            "SELECT id, product_id, quantity FROM cart_items WHERE id=? AND session_key=? LIMIT 1",
            'is', [$cart_item_id, $key]
        );
    }
}
