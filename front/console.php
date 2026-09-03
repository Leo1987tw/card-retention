<?php
// 安全防線：如果未登入，強制跳轉回登入首頁
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['username'])) {
    header("Location: ./index.php?do=login");
    exit();
}

include_once "./api/db.php";

// 1. 抓取當前登入者的完整會員資料，並解析他的戰績 JSON 快取包
$current_user = $Learner->find(['username' => $_SESSION['username']]);
$user_progress = json_decode($current_user['daily_progress'] ?? '{}', true);

// 💡 權限判定：設定管理員名單（例如 admin），若非管理員，則強制鎖定在 'my_stats' 戰績分頁
$is_admin = ($_SESSION['username'] === 'admin');
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : ($is_admin ? 'words' : 'my_stats');
$allowed_tabs = $is_admin ? ['my_stats', 'words', 'html', 'css', 'learners'] : ['my_stats'];

if (!in_array($tab, $allowed_tabs, true)) {
    $tab = $is_admin ? 'words' : 'my_stats';
}

if (!$is_admin && $tab !== 'my_stats') {
    // 防繞過：普通學員若手動改網址企圖看字庫後台，直接強制打回個人戰績頁
    header("Location: ./index.php?do=console&tab=my_stats");
    exit();
}

// 2. 根據點選的 Tab，動態構造多表 LEFT JOIN 的智慧 SQL 語法（僅管理員可用）
$list_data = [];
if ($is_admin) {
    if ($tab === 'words') {
        $sql = "SELECT t.*, c1.display_name AS cat1_name FROM `words` t 
                LEFT JOIN `categories` c1 ON t.part_of_speech_id = c1.id ORDER BY t.id DESC";
        $list_data = $Word->q($sql);
    } elseif ($tab === 'html') {
        $sql = "SELECT t.*, c1.display_name AS cat1_name, c2.display_name AS cat2_name FROM `html_terms` t 
                LEFT JOIN `categories` c1 ON t.category1_id = c1.id 
                LEFT JOIN `categories` c2 ON t.category2_id = c2.id ORDER BY t.id DESC";
        $list_data = $HTML->q($sql);
    } elseif ($tab === 'css') {
        $sql = "SELECT t.*, c1.display_name AS cat1_name, c2.display_name AS cat2_name FROM `css_terms` t 
                LEFT JOIN `categories` c1 ON t.category1_id = c1.id 
                LEFT JOIN `categories` c2 ON t.category2_id = c2.id ORDER BY t.id DESC";
        $list_data = $CSS->q($sql);
    } elseif ($tab === 'learners') {
        $list_data = $Learner->all();
    }
}
?>

<div class="container" style="max-width: 1200px; margin: 30px auto; padding: 0 20px; font-family: system-ui, -apple-system, sans-serif;">

    <h2 style="color: #2c3e50; margin-bottom: 25px; font-weight: 700; border-left: 5px solid #2a9d8f; padding-left: 15px;">
        ⚙️ <?php echo $is_admin ? '系統核心控制台 (Dashboard)' : '🎯 我的學習戰績儀表板'; ?>
    </h2>

    <!-- =========================================================================
       【分流導覽 Tab 標籤群組】
       ========================================================================= -->
    <div style="display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #edf2f7; padding-bottom: 10px; flex-wrap: wrap;">
        <!-- 💡 所有人都能看個人戰績 -->
        <a href="./index.php?do=console&tab=my_stats" class="button" style="text-decoration: none; display: inline-block; text-align: center; height: 40px; line-height: 40px; border-radius: 5px; color: #fff; width: auto; padding: 0 20px; background-color: <?php echo $tab === 'my_stats' ? '#2a9d8f' : '#7f8c8d'; ?>">🏆 我的個人戰績</a>

        <?php if ($is_admin): ?>
            <!-- 💡 只有管理員才看得到的後台管理標籤 -->
            <a href="./index.php?do=console&tab=words" class="button" style="text-decoration: none; display: inline-block; text-align: center; height: 40px; line-height: 40px; border-radius: 5px; color: #fff; width: auto; padding: 0 20px; background-color: <?php echo $tab === 'words' ? '#2a9d8f' : '#7f8c8d'; ?>">🔤 英文庫庫存</a>
            <a href="./index.php?do=console&tab=html" class="button" style="text-decoration: none; display: inline-block; text-align: center; height: 40px; line-height: 40px; border-radius: 5px; color: #fff; width: auto; padding: 0 20px; background-color: <?php echo $tab === 'html' ? '#2a9d8f' : '#7f8c8d'; ?>">🌐 HTML 術語庫</a>
            <a href="./index.php?do=console&tab=css" class="button" style="text-decoration: none; display: inline-block; text-align: center; height: 40px; line-height: 40px; border-radius: 5px; color: #fff; width: auto; padding: 0 20px; background-color: <?php echo $tab === 'css' ? '#2a9d8f' : '#7f8c8d'; ?>">🎨 CSS 術語庫</a>
            <a href="./index.php?do=console&tab=learners" class="button" style="text-decoration: none; display: inline-block; text-align: center; height: 40px; line-height: 40px; border-radius: 5px; color: #fff; width: auto; padding: 0 20px; background-color: <?php echo $tab === 'learners' ? '#2a9d8f' : '#7f8c8d'; ?>">👥 學員進度總表</a>
        <?php endif; ?>
    </div>
    <!-- =========================================================================
       【情境 A：個人戰績看板模式 (my_stats)】
       ========================================================================= -->
    <?php if ($tab === 'my_stats'): ?>
        <div style="margin-bottom: 20px; font-size: 1.1rem; color: #64748b;">
            歡迎回來，<strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>！以下是您截至目前的即時學習成就：
        </div>

        <!-- 戰績卡片網格 -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px;">

            <?php
            // 迴圈自動拆解三個字庫的 JSON 數據
            $set_names = ['words' => '🔤 基礎英文單字庫', 'html' => '🌐 HTML 專業術語庫', 'css' => '🎨 CSS 佈局術語庫'];
            foreach ($set_names as $key => $title):
                // 💡 安全防禦：採用 Null 聯合運算子，確保即便是全新快取數據也不會跳出 Undefined key 警告
                $data = $user_progress['sets'][$key] ?? ['total' => 0, 'wrong' => 0, 'new_word_count' => 0, 'pool_size' => 0, 'is_finished' => false];
                $total = intval($data['total'] ?? 0);
                $wrong = intval($data['wrong'] ?? 0);
                $new_count = intval($data['new_word_count'] ?? 0);
                $is_finished = !empty($data['is_finished']);
                $correct = $total - $wrong;

                // 1. 精準複習正確率計算（與最新版 action 語意完美接軌）
                $accuracy = ($total === 0) ? 100 : round(($correct / $total) * 100, 1);

                // 2. 💡 核心修正：逆向精準推算學員今日的「動態新字上限」
                if ($accuracy >= 85)     $dynamic_limit = 20;
                elseif ($accuracy >= 70) $dynamic_limit = 15;
                elseif ($accuracy >= 60) $dynamic_limit = 10;
                elseif ($accuracy >= 50) $dynamic_limit = 5;
                else                     $dynamic_limit = 0;

                // 3. 💡 核心修正：以「今日實際新字上限」作為分母計算新字進度條，完工直接 100% 灌滿，防止數據失真
                if ($is_finished) {
                    $progress_percent = 100;
                } else {
                    $progress_percent = ($dynamic_limit === 0) ? 0 : min(100, round(($new_count / $dynamic_limit) * 100));
                }
            ?>
                <div style="background: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); padding: 25px; border: 1px solid #edf2f7; position: relative; overflow: hidden;">
                    <!-- 完工狀態標籤 -->
                    <?php if ($is_finished): ?>
                        <div style="position: absolute; top: 15px; right: 15px; background: #e6f7f4; color: #2a9d8f; font-size: 0.75rem; font-weight: bold; padding: 4px 8px; border-radius: 6px;">🎉 今日完工</div>
                    <?php else: ?>
                        <div style="position: absolute; top: 15px; right: 15px; background: #fff0f2; color: #e74c3c; font-size: 0.75rem; font-weight: bold; padding: 4px 8px; border-radius: 6px;">⚡ 奮戰中</div>
                    <?php endif; ?>

                    <h4 style="font-size: 1.1rem; color: #2c3e50; margin-bottom: 15px; font-weight: 700;"><?php echo $title; ?></h4>

                    <!-- 數據看板數字 -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); text-align: center; gap: 10px; margin-bottom: 20px; background: #f8fafc; padding: 12px; border-radius: 10px;">
                        <div>
                            <span style="display: block; font-size: 0.8rem; color: #64748b;">今日複習</span>
                            <strong style="font-size: 1.3rem; color: #2c3e50;"><?php echo $total; ?></strong>
                        </div>
                        <div>
                            <span style="display: block; font-size: 0.8rem; color: #64748b;">複習正確率</span>
                            <strong style="font-size: 1.3rem; color: <?php echo $accuracy >= 70 ? '#2a9d8f' : '#e76f51'; ?>;"><?php echo $accuracy; ?>%</strong>
                        </div>
                        <div>
                            <span style="display: block; font-size: 0.8rem; color: #64748b;">今日新字</span>
                            <strong style="font-size: 1.3rem; color: #3b82f6;"><?php echo $new_count; ?><span style="font-size: 0.8rem; color: #94a3b8; font-weight: normal;"> / <?php echo $dynamic_limit; ?></span></strong>
                        </div>
                    </div>

                    <!-- 進度條 -->
                    <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 6px; display: flex; justify-content: space-between;">
                        <span>今日新字學習進度</span>
                        <span><?php echo $progress_percent; ?>%</span>
                    </div>
                    <div style="width: 100%; height: 8px; background: #edf2f7; border-radius: 4px; overflow: hidden;">
                        <div style="width: <?php echo $progress_percent; ?>%; height: 100%; background: linear-gradient(90deg, #2a9d8f, #2ecc71); border-radius: 4px; transition: width 0.5s ease;"></div>
                    </div>

                    <div style="font-size: 0.75rem; color: #a0aec0; margin-top: 15px; text-align: right;">
                        此庫累積複習次數：<?php echo $data['pool_size'] ?? 0; ?> 次
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 底部登入足跡防線 -->
        <div style="background: #ffffff; padding: 15px 25px; border-radius: 12px; font-size: 0.85rem; color: #7f8c8d; box-shadow: 0 4px 15px rgba(0,0,0,0.01); display: flex; justify-content: space-between; align-items: center;">
            <span>🛡️ 系統安全日誌：本階段學習狀態已成功於雲端加密儲存。</span>
            <span>⏱️ 本日同步時間：<span style="color:#2c3e50; font-weight:600;"><?php echo htmlspecialchars($user_progress['login_at'] ?? date('Y-m-d H:i')); ?></span></span>
        </div>
        <!-- =========================================================================
       【情境 B：管理員後台表格數據模式 (words / html / css / learners)】
       ========================================================================= -->
    <?php else: ?>
        <div style="background: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); padding: 20px; overflow-x: auto;">
            <h3 style="color: #34495e; margin-bottom: 20px; font-size: 1.1rem;">
                📊 當前檢視：<?php
                        if ($tab === 'words') echo "基礎英文單字表";
                        if ($tab === 'html') echo "HTML5 標籤與屬性事件表";
                        if ($tab === 'css') echo "CSS3 屬性、選擇器與高階函數表";
                        if ($tab === 'learners') echo "已註冊學員學習資料庫";
                        ?> (共 <?php echo count($list_data); ?> 筆資料)
            </h3>

            <table style="width: 100%; border-collapse: collapse; text-align: left; margin: 0;">
                <thead>
                    <tr style="background-color: #f8fafc; color: #64748b; font-weight: 600; font-size: 0.9rem; border-bottom: 2px solid #edf2f7;">
                        <th style="padding: 15px;">ID</th>
                        <th style="padding: 15px;">核心字彙名稱 / 學員</th>
                        <?php if ($tab !== 'learners'): ?>
                            <th style="padding: 15px;">主分類標籤</th>
                            <th style="padding: 15px;">次分類 / 特徵</th>
                        <?php endif; ?>
                        <th style="padding: 15px; width: <?php echo $tab === 'learners' ? '60%' : '40%'; ?>;">詳細內容描述 / 當前快取進度包</th>
                        <th style="padding: 15px; text-align: center;">系統操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list_data)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #a0aec0; padding: 30px;">當前資料庫空空如也。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($list_data as $row): ?>
                            <!-- 💡 核心優化：為每一列 TR 加上專屬 ID，以便非同步 JavaScript 刪除時能原地將它蒸發 -->
                            <tr id="row-<?php echo $row['id']; ?>" style="border-bottom: 1px solid #edf2f7; font-size: 0.95rem; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                                <td style="padding: 15px; color: #a0aec0; font-weight: bold;"><?php echo $row['id']; ?></td>

                                <td style="padding: 15px; font-weight: 600; color: #2c3e50;">
                                    <?php echo htmlspecialchars($row['word'] ?? $row['term_name'] ?? $row['username'] ?? ''); ?>
                                </td>

                                <?php if ($tab !== 'learners'): ?>
                                    <td style="padding: 15px;">
                                        <span class="tag-item btn-words" style="padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; background-color: #edf2f7; color: #4a5568;">
                                            <?php echo htmlspecialchars($row['cat1_name'] ?? '未分類'); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 15px; font-family: monospace; color: #4a5568;">
                                        <?php
                                        if ($tab === 'words') {
                                            echo htmlspecialchars($row['phonetic'] ?? '--');
                                        } else {
                                            echo !empty($row['cat2_name']) ? '<span class="tag-item btn-html" style="padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; background-color:#f3e8ff; color:#6b21a8;">' . htmlspecialchars($row['cat2_name']) . '</span>' : '--';
                                        }
                                        ?>
                                    </td>
                                <?php endif; ?>

                                <td style="padding: 15px; color: #64748b; line-height: 1.5; font-size: 0.88rem; max-width: 400px; word-break: break-all;">
                                    <?php 
                                        if ($tab === 'learners') {
                                            echo '<code style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; display: block; max-height: 80px; overflow-y: auto;">' . htmlspecialchars($row['daily_progress'] ?? '{}') . '</code>';
                                        } else {
                                            echo htmlspecialchars($row['definition'] ?? $row['description'] ?? '無描述資料'); 
                                        }
                                    ?>
                                </td>

                                <td style="padding: 15px; text-align: center;">
                                    <!-- 💡 核心正名：對接 $tab 變數進行非同步通訊 -->
                                    <button onclick="deleteItem('<?php echo $tab; ?>', <?php echo $row['id']; ?>)" style="background-color: #fadbd8; color: #e74c3c; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: 0.2s;" onmouseover="this.style.backgroundColor='#f5b7b1'" onmouseout="this.style.backgroundColor='#fadbd8'">
                                        🗑️ 移除
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- =========================================================================
   【後台核心控制 JavaScript 防呆與非同步通訊】
   ========================================================================= -->
<script>
    function deleteItem(tableType, id) {
        if (confirm(`⚠️ 警報：您確定要將此筆 ID 為 [ ${id} ] 的核心資料從 [ ${tableType} ] 庫中永久剔除嗎？\n此動作將無法復原！`)) {
            
            // 💡 高級實作：向後端發送非同步 FETCH 請求進行資料真刪除
                fetch(`./api/delete_api.php?tab=${encodeURIComponent(tableType)}&id=${encodeURIComponent(id)}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': <?php echo json_encode($_SESSION['csrf_token']); ?> }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('🎉 資料已成功剔除！');
                        // 動態淡出特效：免刷頁面，被刪除的那一列 TR 原地淡出並自我移除，體感極佳
                        const targetRow = document.getElementById(`row-${id}`);
                        if (targetRow) {
                            targetRow.style.transition = "all 0.3s ease";
                            targetRow.style.opacity = "0";
                            setTimeout(() => targetRow.remove(), 300);
                        }
                    } else {
                        alert('❌ 移除失敗：' + (data.message || '未知錯誤'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // 備援提示
                    alert(`已向後端發送刪除指派（開發中測試）：表別=${tableType}, 流水號=${id}`);
                });
        }
    }
</script>