// ============================================================================
// 货架位置自动补全功能 - 添加到 count_ops.js
// 将以下代码添加到文件末尾(在最后一个})之前)
// ============================================================================

// === 货架位置自动补全 ===
(function() {
    const shelfLocationInput = document.getElementById('shelf-location');
    const shelfSuggestionsBox = document.getElementById('shelf-location-suggestions');
    const currentLocationHint = document.getElementById('current-location-hint');
    const currentLocationValue = document.getElementById('current-location-value');
    let debounceTimer;

    if (!shelfLocationInput || !shelfSuggestionsBox) return;

    // 输入事件
    shelfLocationInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const keyword = this.value.trim();

        if (keyword.length === 0) {
            shelfSuggestionsBox.style.display = 'none';
            return;
        }

        // 防抖,延迟300ms后请求
        debounceTimer = setTimeout(() => {
            fetch('/mrs/index.php?action=api&endpoint=shelf_location_autocomplete&keyword=' + encodeURIComponent(keyword))
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data && result.data.length > 0) {
                        showShelfSuggestions(result.data);
                    } else {
                        shelfSuggestionsBox.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('货架位置自动补全失败:', error);
                    shelfSuggestionsBox.style.display = 'none';
                });
        }, 300);
    });

    // 显示建议
    function showShelfSuggestions(suggestions) {
        shelfSuggestionsBox.innerHTML = '';
        suggestions.forEach(suggestion => {
            const div = document.createElement('div');
            div.className = 'autocomplete-suggestion';
            div.textContent = suggestion;
            div.style.padding = '10px';
            div.style.cursor = 'pointer';
            div.style.borderBottom = '1px solid #f0f0f0';
            div.addEventListener('click', function() {
                shelfLocationInput.value = suggestion;
                shelfSuggestionsBox.style.display = 'none';
            });
            div.addEventListener('mouseover', function() {
                this.style.backgroundColor = '#f0f7ff';
            });
            div.addEventListener('mouseout', function() {
                this.style.backgroundColor = '';
            });
            shelfSuggestionsBox.appendChild(div);
        });
        shelfSuggestionsBox.style.display = 'block';
    }

    // 点击外部关闭
    document.addEventListener('click', function(e) {
        if (e.target !== shelfLocationInput && !shelfSuggestionsBox.contains(e.target)) {
            shelfSuggestionsBox.style.display = 'none';
        }
    });

    // 获得焦点时显示常用位置
    shelfLocationInput.addEventListener('focus', function() {
        if (this.value.trim().length === 0) {
            fetch('/mrs/index.php?action=api&endpoint=shelf_location_autocomplete')
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data && result.data.length > 0) {
                        showShelfSuggestions(result.data);
                    }
                })
                .catch(error => console.error('获取常用位置失败:', error));
        }
    });

    // 暴露函数供openCountModal使用
    window.setCurrentShelfLocation = function(location) {
        if (location) {
            currentLocationValue.textContent = location;
            currentLocationHint.style.display = 'block';
        } else {
            currentLocationValue.textContent = '';
            currentLocationHint.style.display = 'none';
        }
    };
})();

// ============================================================================
// 修改 openCountModal 函数
// 在第377行 systemInfoContainer.innerHTML = systemInfo; 之后添加:
// ============================================================================
/*
        // 显示当前货架位置
        const shelfLocationInput = document.getElementById('shelf-location');
        if (shelfLocationInput) {
            shelfLocationInput.value = '';  // 清空输入框
            if (typeof window.setCurrentShelfLocation === 'function') {
                window.setCurrentShelfLocation(boxData.warehouse_location || null);
            }
        }
*/

// ============================================================================
// 修改保存清点记录函数
// 在第446行 formData.append('remark', countRemark.value.trim()); 之后添加:
// ============================================================================
/*
            // 添加货架位置
            const shelfLocationInput = document.getElementById('shelf-location');
            if (shelfLocationInput) {
                formData.append('shelf_location', shelfLocationInput.value.trim());
            }
*/

// ============================================================================
// CSS样式添加到 count.css
// ============================================================================
/*
.autocomplete-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 200px;
    overflow-y: auto;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    z-index: 1000;
}

.autocomplete-suggestion:last-child {
    border-bottom: none;
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 0.9em;
}
*/
