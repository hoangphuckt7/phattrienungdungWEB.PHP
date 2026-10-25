<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Lớp xử lý dữ liệu dùng chung cho phần cửa hàng.
 * Các dữ liệu sản phẩm, danh mục, bài viết và đơn hàng được đọc/ghi trong MySQL;
 * riêng giỏ hàng vẫn được lưu trong session của khách đang truy cập.
 */
class DataHelper extends csdltmdt
{
    /** Mở kết nối MySQL thông qua lớp cấu hình csdltmdt. */
    private function db(): mysqli { return $this->connect(); }

    /**
     * Bổ sung dữ liệu liên quan cho một sản phẩm vừa đọc từ bảng products.
     * Nạp thương hiệu nếu thiếu, chuẩn hóa kiểu giá/tồn kho/cờ trạng thái,
     * rồi lấy thêm danh sách màu, dung lượng và thông số kỹ thuật từ các bảng con.
     *
     * @param array $row Một dòng sản phẩm đã có các cột cơ bản.
     * @return array Sản phẩm có cấu trúc mà các trang giao diện đang sử dụng.
     */
    private function productDetails(array $row): array
    {
        $db = $this->db();
        $id = (int)$row['id'];
        $row['brand'] = $row['brand'] ?? '';
        $row['category_id'] = (int)$row['category_id'];
        $row['sale_price'] = $row['sale_price'] !== null ? (float)$row['sale_price'] : null;
        $row['price'] = (float)$row['price'];
        $row['stock'] = (int)$row['stock'];
        foreach (['is_new', 'is_featured', 'is_bestseller'] as $flag) $row[$flag] = (bool)$row[$flag];

        $row['colors'] = [];
        $stmt = $db->prepare('SELECT color_name FROM product_colors WHERE product_id=? ORDER BY id');
        $stmt->bind_param('i', $id); $stmt->execute();
        foreach ($stmt->get_result() as $r) $row['colors'][] = $r['color_name'];
        $stmt->close();

        $row['storage'] = [];
        $stmt = $db->prepare('SELECT storage FROM product_storages WHERE product_id=? ORDER BY id');
        $stmt->bind_param('i', $id); $stmt->execute();
        foreach ($stmt->get_result() as $r) $row['storage'][] = $r['storage'];
        $stmt->close();

        $row['specs'] = [];
        $stmt = $db->prepare('SELECT spec_name, spec_value FROM product_specs WHERE product_id=? ORDER BY sort_order, id');
        $stmt->bind_param('i', $id); $stmt->execute();
        foreach ($stmt->get_result() as $r) $row['specs'][$r['spec_name']] = $r['spec_value'];
        $stmt->close();
        return $row;
    }

    /**
     * Lấy danh sách sản phẩm đang hoạt động, áp dụng các bộ lọc tùy chọn.
     * Các bộ lọc hỗ trợ: keyword, category, brand, min_price, max_price, sort.
     * Giá lọc và sắp xếp là giá khuyến mãi nếu có, nếu không dùng giá niêm yết.
     *
     * @param array $filters Bộ lọc nhận từ trang danh sách sản phẩm.
     * @return array Danh sách sản phẩm kèm màu, dung lượng và thông số kỹ thuật.
     */
    public function get_all_products(array $filters = []): array
    {
        $db = $this->db();
        $sql = "SELECT p.*, b.name AS brand FROM products p
                LEFT JOIN brands b ON b.id=p.brand_id WHERE p.status=1";
        $types = ''; $params = [];
        if (!empty($filters['keyword'])) {
            $sql .= ' AND (p.name LIKE ? OR b.name LIKE ?)';
            $like = '%' . trim($filters['keyword']) . '%'; $types .= 'ss'; array_push($params, $like, $like);
        }
        if (isset($filters['category']) && $filters['category'] !== '') {
            $sql .= ' AND p.category_id=?'; $types .= 'i'; $params[] = (int)$filters['category'];
        }
        if (!empty($filters['brand'])) { $sql .= ' AND LOWER(b.name)=LOWER(?)'; $types .= 's'; $params[] = $filters['brand']; }
        if (isset($filters['min_price']) && $filters['min_price'] !== '') { $sql .= ' AND COALESCE(NULLIF(p.sale_price,0),p.price)>=?'; $types .= 'd'; $params[] = (float)$filters['min_price']; }
        if (isset($filters['max_price']) && $filters['max_price'] !== '') { $sql .= ' AND COALESCE(NULLIF(p.sale_price,0),p.price)<=?'; $types .= 'd'; $params[] = (float)$filters['max_price']; }
        $sql .= match ($filters['sort'] ?? '') {
            'price_asc' => ' ORDER BY COALESCE(NULLIF(p.sale_price,0),p.price) ASC',
            'price_desc' => ' ORDER BY COALESCE(NULLIF(p.sale_price,0),p.price) DESC',
            'newest' => ' ORDER BY p.created_at DESC, p.id DESC',
            default => ' ORDER BY p.is_featured DESC, p.id DESC'
        };
        $stmt = $db->prepare($sql);
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return array_map(fn($row) => $this->productDetails($row), $rows);
    }

    /** Lấy một sản phẩm đang hoạt động theo ID; trả về null nếu không tìm thấy. */
    public function get_product_by_id($id): ?array
    {
        $db = $this->db(); $id = (int)$id;
        $stmt = $db->prepare('SELECT p.*, b.name AS brand FROM products p LEFT JOIN brands b ON b.id=p.brand_id WHERE p.id=? AND p.status=1 LIMIT 1');
        $stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return $row ? $this->productDetails($row) : null;
    }

    /**
     * Lấy các danh mục cấp cao nhất đang hoạt động.
     * Kết quả được đánh chỉ mục theo ID để giao diện có thể tra nhanh bằng category_id.
     */
    public function get_all_categories(): array
    {
        $result = $this->db()->query('SELECT id, name, icon, description FROM categories WHERE status=1 AND parent_id IS NULL ORDER BY sort_order, id');
        $rows = [];
        while ($row = $result->fetch_assoc()) { $row['id'] = (int)$row['id']; $rows[$row['id']] = $row; }
        return $rows;
    }

    /**
     * Lấy bài viết đã xuất bản và đang hiển thị công khai.
     * Nếu truyền từ khóa, tìm trong tiêu đề và phần tóm tắt; kết quả mới nhất đứng trước.
     */
    public function get_all_articles(string $search = ''): array
    {
        $db = $this->db();
        // Xóa bỏ is_visible=1 khỏi câu lệnh WHERE
        $sql = "SELECT id, article_code, category_id, title, summary, content, thumbnail, author, view_count, status, is_visible, published_at
                FROM articles WHERE status='Xuất bản'";
        if ($search !== '') {
            $sql .= ' AND (title LIKE ? OR summary LIKE ?) ORDER BY published_at DESC, id DESC';
            $stmt = $db->prepare($sql); 
            $like = '%' . trim($search) . '%'; 
            $stmt->bind_param('ss', $like, $like);
        } else {
            $sql .= ' ORDER BY published_at DESC, id DESC'; 
            $stmt = $db->prepare($sql);
        }
        $stmt->execute(); 
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
        $stmt->close(); 
        return $rows;
    }

    /** Lấy bài viết công khai theo ID; bài nháp, ẩn hoặc không tồn tại trả về null. */
    public function get_article_by_id($id): ?array
    {
        $db = $this->db(); $id = (int)$id;
        // Xóa bỏ is_visible=1 khỏi câu lệnh WHERE
        $stmt = $db->prepare("SELECT id, article_code, category_id, title, summary, content, thumbnail, author, view_count, status, is_visible, published_at FROM articles WHERE id=? AND status='Xuất bản' LIMIT 1");
        $stmt->bind_param('i', $id); 
        $stmt->execute(); 
        $row = $stmt->get_result()->fetch_assoc(); 
        $stmt->close(); 
        return $row ?: null;
    }
	/** Tăng số lượt xem của bài viết lên 1 */
public function increment_article_view($id): void
{
    $db = $this->db();
    $id = (int)$id;
    $stmt = $db->prepare('UPDATE articles SET view_count = view_count + 1 WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}
    /** Trả về các mặt hàng trong giỏ hiện tại; giỏ chưa tạo thì trả về mảng rỗng. */
    public function get_cart_items(): array { return $_SESSION['cart'] ?? []; }

    /**
     * Thêm sản phẩm vào giỏ session. Kiểm tra sản phẩm, tồn kho và tùy chọn màu/dung lượng;
     * các lần thêm cùng sản phẩm và cùng tùy chọn được gộp thành một dòng.
     * Số lượng luôn bị giới hạn theo tồn kho hiện tại.
     */
    public function add_to_cart($product_id, $quantity = 1, $color = '', $storage = ''): bool
    {
        $p = $this->get_product_by_id($product_id);
        if (!$p || $p['stock'] < 1) return false;
        $colors = $p['colors']; $storages = $p['storage'];
        if ($color === '' && $colors) $color = $colors[0];
        if ($storage === '' && $storages) $storage = $storages[0];
        if (($colors && !in_array($color, $colors, true)) || ($storages && !in_array($storage, $storages, true))) return false;
        $quantity = min(max(1, (int)$quantity), $p['stock']);
        $key = $p['id'] . '_' . md5($color . '_' . $storage);
        $current = $_SESSION['cart'][$key]['quantity'] ?? 0;
        $_SESSION['cart'][$key] = ['key'=>$key, 'product_id'=>$p['id'], 'name'=>$p['name'], 'image'=>$p['image'], 'color'=>$color, 'storage'=>$storage,
            'price'=>($p['sale_price'] && $p['sale_price'] < $p['price']) ? $p['sale_price'] : $p['price'], 'quantity'=>min($p['stock'], $current+$quantity)];
        return true;
    }

    /** Cập nhật số lượng của một dòng giỏ; số lượng 0 hoặc nhỏ hơn sẽ xóa dòng đó. */
    public function update_cart_quantity($item_key, $quantity): void
    {
        if (!isset($_SESSION['cart'][$item_key])) return;
        $quantity = (int)$quantity;
        if ($quantity <= 0) { unset($_SESSION['cart'][$item_key]); return; }
        $p = $this->get_product_by_id($_SESSION['cart'][$item_key]['product_id']);
        if (!$p || $p['stock'] < 1) { unset($_SESSION['cart'][$item_key]); return; }
        $_SESSION['cart'][$item_key]['quantity'] = min($quantity, $p['stock']);
    }
    /** Xóa riêng một dòng giỏ theo khóa item_key. */
    public function remove_from_cart($key): void { unset($_SESSION['cart'][$key]); }
    /** Xóa toàn bộ giỏ hàng của session hiện tại. */
    public function clear_cart(): void { $_SESSION['cart'] = []; }

    /** Tính tổng tiền và tổng số lượng của giỏ dựa trên giá/tồn kho mới nhất trong DB. */
    public function calculate_cart_totals(): array
    {
        $amount = 0; $count = 0;
        foreach ($this->get_cart_items() as $item) { $p = $this->get_product_by_id($item['product_id']); if (!$p) continue;
            $price = ($p['sale_price'] && $p['sale_price'] < $p['price']) ? $p['sale_price'] : $p['price'];
            $amount += $price * (int)$item['quantity']; $count += (int)$item['quantity']; }
        return ['total_amount'=>$amount, 'total_count'=>$count];
    }

    /**
     * Tạo đơn hàng từ giỏ hiện tại trong một transaction.
     * Khóa và kiểm tra từng sản phẩm để tránh bán vượt tồn kho, ghi orders,
     * order_items và payments, trừ kho; chỉ xóa giỏ sau khi commit thành công.
     * Trả về dữ liệu đơn theo cấu trúc mà checkout.php đang dùng, hoặc false nếu thất bại.
     */
    public function create_order_from_cart(array $shipping_info, string $payment_method): array|false
    {
        $cart = $this->get_cart_items();
        if (!$cart) return false;

        $db = $this->db();
        $db->begin_transaction();
        try {
            $prepared = [];
            $total = 0;
            foreach ($cart as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                $stmt = $db->prepare('SELECT p.id,p.name,p.price,p.sale_price,p.stock FROM products p WHERE p.id=? AND p.status=1 FOR UPDATE');
                $stmt->bind_param('i', $pid);
                $stmt->execute();
                $p = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$p || $qty < 1 || (int)$p['stock'] < $qty) throw new RuntimeException('Sản phẩm không đủ tồn kho.');
                $price = ($p['sale_price'] !== null && $p['sale_price'] > 0 && $p['sale_price'] < $p['price']) ? (float)$p['sale_price'] : (float)$p['price'];
                $prepared[] = [$p,$item,$qty,$price]; $total += $qty * $price;
            }
            $orderCode = 'IT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $method = $payment_method === 'online' ? 'VNPAY_QR' : 'COD';
            $payStatus = $payment_method === 'online' ? 'Chờ xác nhận' : 'Chưa thanh toán';
            $orderStatus = 'Chờ xác nhận';
            $name=$shipping_info['name']; $phone=$shipping_info['phone']; $email=$shipping_info['email']; $address=$shipping_info['address']; $note=$shipping_info['note'] ?? '';
            $stmt = $db->prepare('INSERT INTO orders (order_code,user_id,receiver_name,phone,email,address,note,payment_method,payment_status,order_status,total_amount) VALUES (?,NULL,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('sssssssssd', $orderCode,$name,$phone,$email,$address,$note,$method,$payStatus,$orderStatus,$total);
            $stmt->execute(); $dbOrderId = $db->insert_id; $stmt->close();
            $itemStmt = $db->prepare('INSERT INTO order_items (order_id,product_id,product_name,color,storage,unit_price,quantity) VALUES (?,?,?,?,?,?,?)');
            $stockStmt = $db->prepare('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?');
            foreach ($prepared as [$p,$item,$qty,$price]) {
                $pid=(int)$p['id']; $pname=$p['name']; $color=$item['color'] ?: null; $storage=$item['storage'] ?: null;
                $itemStmt->bind_param('iisssdi',$dbOrderId,$pid,$pname,$color,$storage,$price,$qty); $itemStmt->execute();
                $stockStmt->bind_param('iii',$qty,$pid,$qty); $stockStmt->execute(); if ($stockStmt->affected_rows !== 1) throw new RuntimeException('Không thể cập nhật tồn kho.');
            }
            $itemStmt->close(); $stockStmt->close();
            if ($payment_method !== 'online') {
                $paymentCode = 'PAY-' . strtoupper(bin2hex(random_bytes(4)));
                $paymentStatus = 'COD - Chờ thu tiền';
                $stmt=$db->prepare('INSERT INTO payments (payment_code,order_id,method,amount,status) VALUES (?,?,?,?,?)');
                $stmt->bind_param('sisds',$paymentCode,$dbOrderId,$method,$total,$paymentStatus);
                $stmt->execute();
                $stmt->close();
            }
            $db->commit(); $this->clear_cart();
            return ['id'=>$orderCode,'customer_name'=>$name,'customer_phone'=>$phone,'customer_email'=>$email,'customer_address'=>$address,'note'=>$note,'total_amount'=>$total,
                'payment_method'=>$payment_method,'payment_status'=>$payment_method === 'online' ? 'unpaid' : 'unpaid','order_status'=>'pending','created_at'=>date('Y-m-d H:i:s')];
        } catch (Throwable $e) { $db->rollback(); error_log('Create order failed: ' . $e->getMessage()); return false; }
    }

    /**
     * Đánh dấu một đơn VNPay sẵn sàng thanh toán và trả về số tiền cần gửi cổng.
     * Đơn đã thanh toán hoặc đã hủy không được phép tạo lại URL thanh toán.
     * Khi khách thử lại sau giao dịch lỗi, trạng thái thanh toán được đưa về chờ xác nhận.
     */
    public function prepare_vnpay_payment(string $orderCode): array|false
    {
        $db = $this->db();
        $db->begin_transaction();
        try {
            $stmt = $db->prepare('SELECT id, total_amount, payment_method, payment_status, order_status FROM orders WHERE order_code=? FOR UPDATE');
            $stmt->bind_param('s', $orderCode);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order || $order['payment_method'] !== 'VNPAY_QR' || $order['payment_status'] === 'Đã thanh toán' || $order['order_status'] === 'Đã hủy') {
                $db->rollback();
                return false;
            }

            $dbOrderId = (int)$order['id'];
            // Mỗi lần thử dùng một payment_code riêng làm vnp_TxnRef để không trùng giao dịch cũ.
            $paymentCode = 'PAY-' . strtoupper(bin2hex(random_bytes(4)));
            $method = 'VNPAY_QR';
            $amount = (float)$order['total_amount'];
            $status = 'Chờ xác nhận';
            $stmt = $db->prepare('INSERT INTO payments (payment_code,order_id,method,amount,status) VALUES (?,?,?,?,?)');
            $stmt->bind_param('sisds', $paymentCode, $dbOrderId, $method, $amount, $status);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare("UPDATE orders SET payment_status='Chờ xác nhận' WHERE id=?");
            $stmt->bind_param('i', $dbOrderId);
            $stmt->execute();
            $stmt->close();

            $db->commit();
            return ['order_id'=>$dbOrderId, 'order_code'=>$orderCode, 'txn_ref'=>$paymentCode, 'amount'=>(int)$order['total_amount']];
        } catch (Throwable $e) {
            $db->rollback();
            error_log('Prepare VNPay payment failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ghi nhận kết quả VNPay từ IPN sau khi endpoint đã xác thực vnp_SecureHash.
     * Mã đơn, phương thức và số tiền được đối chiếu với database; callback sai bị từ chối.
     * Trạng thái đã thanh toán không thể bị hạ xuống bởi một callback thất bại đến muộn.
     */
    public function record_vnpay_result(array $params): array
    {
        $txnRef = (string)($params['vnp_TxnRef'] ?? '');
        $gatewayAmount = filter_var($params['vnp_Amount'] ?? null, FILTER_VALIDATE_INT);
        $transactionNo = substr((string)($params['vnp_TransactionNo'] ?? ''), 0, 60);
        if ($txnRef === '') {
            return ['valid'=>false, 'paid'=>false, 'rsp_code'=>'01'];
        }
        if ($gatewayAmount === false || $gatewayAmount < 0) {
            return ['valid'=>false, 'paid'=>false, 'rsp_code'=>'04'];
        }

        $success = ($params['vnp_ResponseCode'] ?? '') === '00' && ($params['vnp_TransactionStatus'] ?? '') === '00';
        // VNPay response code 24 means the customer cancelled the transaction.
        $cancelled = ($params['vnp_ResponseCode'] ?? '') === '24';
        $db = $this->db();
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("SELECT p.id AS payment_id, p.order_id, p.amount AS payment_amount, p.status AS payment_status, o.order_code, o.payment_status AS order_payment_status, o.order_status FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.payment_code=? AND p.method='VNPAY_QR' FOR UPDATE");
            $stmt->bind_param('s', $txnRef);
            $stmt->execute();
            $payment = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$payment) {
                $db->rollback();
                return ['valid'=>false, 'paid'=>false, 'rsp_code'=>'01'];
            }
            if ((int)$payment['payment_amount'] * 100 !== $gatewayAmount) {
                $db->rollback();
                return ['valid'=>false, 'paid'=>false, 'rsp_code'=>'04'];
            }

            $dbOrderId = (int)$payment['order_id'];
            $paymentId = (int)$payment['payment_id'];
            $alreadyPaid = $payment['order_payment_status'] === 'Đã thanh toán';
            if ($payment['payment_status'] !== 'Chờ xác nhận') {
                $db->commit();
                return ['valid'=>true, 'paid'=>$payment['payment_status'] === 'Thành công' || $alreadyPaid, 'already_processed'=>true, 'order_code'=>$payment['order_code'], 'rsp_code'=>'02'];
            }

            if ($success) {
                $stmt = $db->prepare("UPDATE orders SET payment_status='Đã thanh toán' WHERE id=? AND order_status<>'Đã hủy'");
                $stmt->bind_param('i', $dbOrderId);
                $stmt->execute();
                $orderPaid = $stmt->affected_rows === 1 || $alreadyPaid;
                $stmt->close();
                if (!$orderPaid) {
                    $db->rollback();
                    return ['valid'=>false, 'paid'=>false, 'rsp_code'=>'99'];
                }
                $paymentStatus = 'Thành công';
            } else {
                if (!$alreadyPaid) {
                    $failedStatus = 'Thất bại';
                    $cancelledStatus = 'Đã hủy';
                    if ($cancelled) {
                        $stmt = $db->prepare('UPDATE orders SET payment_status=?, order_status=? WHERE id=?');
                        $stmt->bind_param('ssi', $cancelledStatus, $cancelledStatus, $dbOrderId);
                    } else {
                        $stmt = $db->prepare('UPDATE orders SET payment_status=? WHERE id=? AND order_status<>?');
                        $stmt->bind_param('sis', $failedStatus, $dbOrderId, $cancelledStatus);
                    }
                    $stmt->execute();
                    $stmt->close();
                }
                $paymentStatus = $alreadyPaid
                    ? 'Thành công'
                    : ($cancelled ? 'Đã hủy' : 'Thất bại');
            }

            $stmt = $db->prepare("UPDATE payments SET status=?, reference_code=COALESCE(NULLIF(?,''),reference_code), confirmed_at=IF(?='Thành công',NOW(),confirmed_at) WHERE id=?");
            $stmt->bind_param('sssi', $paymentStatus, $transactionNo, $paymentStatus, $paymentId);
            $stmt->execute();
            $stmt->close();
            $db->commit();
            return ['valid'=>true, 'paid'=>$paymentStatus === 'Thành công', 'already_processed'=>false, 'order_code'=>$payment['order_code'], 'rsp_code'=>'00'];
        } catch (Throwable $e) {
            $db->rollback();
            error_log('Record VNPay result failed: ' . $e->getMessage());
            return ['valid'=>false, 'paid'=>false, 'rsp_code'=>'99'];
        }
    }

    /** Kiểm tra tham chiếu VNPay và số tiền rồi đọc đơn tương ứng mà không đổi trạng thái DB. */
    public function get_vnpay_order_by_txn_ref(string $txnRef, int $gatewayAmount): ?array
    {
        $db = $this->db();
        $stmt = $db->prepare("SELECT o.order_code, p.amount FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.payment_code=? AND p.method='VNPAY_QR' LIMIT 1");
        $stmt->bind_param('s', $txnRef);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$payment || (int)$payment['amount'] * 100 !== $gatewayAmount) return null;
        return $this->get_order_by_id($payment['order_code']);
    }

    /** Tìm đơn theo mã order_code và trả về cấu trúc tương thích với trang xác nhận đơn. */
    public function get_order_by_id($id): ?array
    {
        $db=$this->db(); $stmt=$db->prepare('SELECT * FROM orders WHERE order_code=? LIMIT 1'); $stmt->bind_param('s',$id); $stmt->execute(); $r=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$r) return null;
        $paidStatus = 'Đã thanh toán';
        $failedStatus = 'Thất bại';
        $cancelledStatus = 'Đã hủy';
        $mappedPaymentStatus = $r['payment_status'] === $paidStatus
            ? 'paid'
            : ($r['payment_status'] === $failedStatus ? 'failed' : ($r['payment_status'] === $cancelledStatus ? 'cancelled' : 'unpaid'));
        $order=['id'=>$r['order_code'],'customer_name'=>$r['receiver_name'],'customer_phone'=>$r['phone'],'customer_email'=>$r['email'],'customer_address'=>$r['address'],'note'=>$r['note'],
            'total_amount'=>(float)$r['total_amount'],'payment_method'=>$r['payment_method']==='COD'?'cod':'online','payment_status'=>$mappedPaymentStatus,'order_status'=>$r['order_status'],'created_at'=>$r['created_at'],'items'=>[]];
        $oid=(int)$r['id']; $stmt=$db->prepare('SELECT product_id,product_name,color,storage,unit_price AS price,quantity,unit_price*quantity AS subtotal FROM order_items WHERE order_id=?');
        $stmt->bind_param('i',$oid); $stmt->execute(); $order['items']=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $order;
    }
	/** Lấy danh sách các bài viết liên quan cùng danh mục, loại trừ bài viết hiện tại */
public function get_related_articles(int $category_id, int $current_article_id, int $limit = 3): array
{
    $db = $this->db();
    // Lấy bài cùng category_id, loại bỏ bài hiện tại, trạng thái 'Xuất bản'
    $sql = "SELECT id, title, thumbnail, published_at 
            FROM articles 
            WHERE category_id = ? AND id != ? AND status = 'Xuất bản' 
            ORDER BY published_at DESC, id DESC LIMIT ?";
            
    $stmt = $db->prepare($sql);
    $stmt->bind_param('iii', $category_id, $current_article_id, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $rows;
}
}

$dataHelper = new DataHelper();
// Các hàm toàn cục bên dưới giữ tương thích với những trang PHP cũ đang gọi hàm trực tiếp.
/** Hàm cầu nối lấy danh sách sản phẩm và chuyển bộ lọc cho DataHelper. */
function get_all_products($filters=[]){ global $dataHelper; return $dataHelper->get_all_products($filters); }
/** Hàm cầu nối lấy một sản phẩm theo ID. */
function get_product_by_id($id){ global $dataHelper; return $dataHelper->get_product_by_id($id); }
/** Hàm cầu nối lấy danh mục cấp cao nhất. */
function get_all_categories(){ global $dataHelper; return $dataHelper->get_all_categories(); }
/** Hàm cầu nối lấy bài viết đã xuất bản, có thể kèm từ khóa tìm kiếm. */
function get_all_articles($search=''){ global $dataHelper; return $dataHelper->get_all_articles($search); }
/** Hàm cầu nối lấy một bài viết công khai theo ID. */
function get_article_by_id($id){ global $dataHelper; return $dataHelper->get_article_by_id($id); }
/** Hàm cầu nối lấy giỏ hàng hiện tại trong session. */
function get_cart_items(){ global $dataHelper; return $dataHelper->get_cart_items(); }
/** Hàm cầu nối thêm sản phẩm vào giỏ, nhận ID, số lượng, màu và dung lượng. */
function add_to_cart($id,$quantity=1,$color='',$storage=''){ global $dataHelper; return $dataHelper->add_to_cart($id,$quantity,$color,$storage); }
/** Hàm cầu nối cập nhật số lượng hoặc xóa dòng nếu số lượng không dương. */
function update_cart_quantity($key,$quantity){ global $dataHelper; return $dataHelper->update_cart_quantity($key,$quantity); }
/** Hàm cầu nối xóa một dòng giỏ theo khóa. */
function remove_from_cart($key){ global $dataHelper; return $dataHelper->remove_from_cart($key); }
/** Hàm cầu nối làm trống giỏ hiện tại. */
function clear_cart(){ global $dataHelper; return $dataHelper->clear_cart(); }
/** Hàm cầu nối tính tổng tiền và số lượng món hàng trong giỏ. */
function calculate_cart_totals(){ global $dataHelper; return $dataHelper->calculate_cart_totals(); }
/** Hàm cầu nối tạo đơn hàng từ giỏ và thông tin giao nhận/thanh toán. */
function create_order_from_cart($info,$method){ global $dataHelper; return $dataHelper->create_order_from_cart($info,$method); }
/** Hàm cầu nối đặt đơn online vào trạng thái có thể gửi sang VNPay. */
function prepare_vnpay_payment($orderCode){ global $dataHelper; return $dataHelper->prepare_vnpay_payment($orderCode); }
/** Hàm cầu nối ghi kết quả callback VNPay đã được xác minh chữ ký. */
function record_vnpay_result($params){ global $dataHelper; return $dataHelper->record_vnpay_result($params); }
/** Hàm cầu nối tra cứu đơn từ mã giao dịch VNPay và xác nhận số tiền callback. */
function get_vnpay_order_by_txn_ref($txnRef,$amount){ global $dataHelper; return $dataHelper->get_vnpay_order_by_txn_ref($txnRef,$amount); }
/** Hàm cầu nối tra cứu đơn hàng theo mã đơn. */
function get_order_by_id($id){ global $dataHelper; return $dataHelper->get_order_by_id($id); }
/** Hàm cầu nối tăng lượt xem bài viết */
function increment_article_view($id){ global $dataHelper; return $dataHelper->increment_article_view($id); }
/** Hàm cầu nối lấy bài viết liên quan */
function get_related_articles($cat_id, $current_id, $limit = 3){ global $dataHelper; return $dataHelper->get_related_articles($cat_id, $current_id, $limit); }
