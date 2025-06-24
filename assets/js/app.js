// Notification System
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Trigger reflow for animation
    notification.offsetHeight;
    notification.classList.add('show');
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Delete Confirmation Modal
function showDeleteConfirmation(url, itemType) {
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.style.display = 'flex';
    
    const modal = document.createElement('div');
    modal.className = 'modal';
    
    modal.innerHTML = `
        <div class="modal-header">
            <h3>Confirm Deletion</h3>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this ${itemType}? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="this.closest('.modal-backdrop').remove()">Cancel</button>
            <button class="btn btn-danger" onclick="confirmDelete('${url}')">Delete</button>
        </div>
    `;
    
    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);
    // NEW: Add the "show" class after a brief delay to trigger transitions
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function confirmDelete(url) {
    window.location.href = url;
}

// Stats Animation
document.addEventListener('DOMContentLoaded', function() {
    const values = document.querySelectorAll('.value');
    values.forEach(value => {
        const amount = parseFloat(value.textContent.replace(/[^0-9.-]+/g, ""));
        value.setAttribute('data-value', amount);
        value.textContent = 'MAD 0'; // Updated initial display
        
        setTimeout(() => {
            animateValue(value, 0, amount, 1000);
        }, 300);
    });
});

function animateValue(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    // NEW: Check if the element should display as percentage
    const formatType = element.getAttribute('data-format'); 
    const animate = () => {
        current += increment;
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            if (formatType === 'percentage') {
                element.textContent = formatPercentage(end);
            } else {
                element.textContent = formatCurrency(end);
            }
            return;
        }
        if (formatType === 'percentage') {
            element.textContent = formatPercentage(current);
        } else {
            element.textContent = formatCurrency(current);
        }
        requestAnimationFrame(animate);
    };
    animate();
}

function formatPercentage(value) {
    return value.toFixed(1) + "%";
}

// Existing function for currency formatting remains unchanged
function formatCurrency(value) {
    return 'MAD ' + value.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Chart configuration options
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            grid: {
                color: 'rgba(255, 255, 255, 0.1)'
            },
            ticks: {
                color: 'rgba(255, 255, 255, 0.7)',
                callback: function(value) {
                    return 'MAD ' + value.toLocaleString();
                }
            }
        },
        x: {
            grid: {
                color: 'rgba(255, 255, 255, 0.1)'
            },
            ticks: {
                color: 'rgba(255, 255, 255, 0.7)',
                maxRotation: 45,
                minRotation: 45
            }
        }
    },
    plugins: {
        tooltip: {
            callbacks: {
                label: function(context) {
                    return 'Balance: MAD ' + context.parsed.y.toLocaleString();
                }
            }
        },
        legend: {
            display: false
        }
    },
    interaction: {
        intersect: false,
        mode: 'index'
    }
};

// Chart Functionality
let balanceChart = null;
let categoryPieChart = null;
let trendChart = null;

async function fetchChartData(type, period) {
    try {
        const response = await fetch(`get_chart_data.php?type=${type}&period=${period}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        console.log(`Fetched ${type} data for ${period}:`, data);
        return data;
    } catch (error) {
        console.error('Error fetching chart data:', error);
        showNotification('Error loading chart data', 'error');
        return null;
    }
}

async function updateChartPeriod(period) {
    try {
        const data = await fetchChartData('balance', period);
        if (!data || !Array.isArray(data)) {
            console.error('Invalid balance data received:', data);
            return;
        }

        const ctx = document.getElementById('balanceChart').getContext('2d');
        
        // Update active button state
        document.querySelectorAll('[onclick*="updateChartPeriod"]').forEach(btn => {
            btn.classList.toggle('active', btn.textContent.toLowerCase().includes(period));
        });
        
        if (balanceChart) {
            balanceChart.destroy();
        }

        // Ensure we have valid numeric values
        const chartData = data.map(item => ({
            date: formatDate(item.date),
            balance: parseFloat(item.balance) || 0
        })).filter(item => !isNaN(item.balance));

        console.log('Processed chart data:', chartData);

        if (chartData.length === 0) {
            console.log('No valid data points for chart');
            return;
        }

        const minBalance = Math.min(...chartData.map(item => item.balance));
        const maxBalance = Math.max(...chartData.map(item => item.balance));
        const padding = Math.abs(maxBalance - minBalance) * 0.1;

        balanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(item => item.date),
                datasets: [{
                    label: 'Balance Over Time',
                    data: chartData.map(item => item.balance),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                ...chartOptions,
                scales: {
                    ...chartOptions.scales,
                    y: {
                        ...chartOptions.scales.y,
                        suggestedMin: Math.min(0, minBalance - padding),
                        suggestedMax: maxBalance + padding
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error updating balance chart:', error);
        showNotification('Error updating chart', 'error');
    }
}

async function updatePieChartPeriod(period) {
    try {
        const categoryData = await fetchChartData('categories', period);
        if (!categoryData) {
            console.error('Invalid category data received');
            return;
        }

        const income = parseFloat(categoryData.income) || 0;
        const expenses = parseFloat(categoryData.charge) || 0;

        // Update Pie Chart
        const pieCtx = document.getElementById('categoryPieChart').getContext('2d');
        if (categoryPieChart) {
            categoryPieChart.destroy();
        }

        categoryPieChart = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Income', 'Expenses'],
                datasets: [{
                    data: [income, expenses],
                    backgroundColor: ['#22c55e', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const total = income + expenses;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${context.label}: MAD ${value.toLocaleString(undefined, {minimumFractionDigits: 2})} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Update active button state
        document.querySelectorAll('[onclick*="updatePieChartPeriod"]').forEach(btn => {
            btn.classList.toggle('active', btn.textContent.toLowerCase().includes(period));
        });

    } catch (error) {
        console.error('Error updating pie chart:', error);
        showNotification('Error updating charts', 'error');
    }
}

// Format date for chart display
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        year: 'numeric'
    });
}

// Initialize charts when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize only the balance chart with All Time view
    updateChartPeriod('all');
});

// Mobile-specific enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Optimize chart responsiveness
    const resizeCharts = () => {
        const charts = document.querySelectorAll('canvas');
        charts.forEach(chart => {
            if (chart.chart) {
                chart.chart.resize();
            }
        });
    };

    // Handle viewport changes
    window.addEventListener('resize', resizeCharts);
    
    // Add touch support for charts
    const chartContainers = document.querySelectorAll('.chart-container');
    chartContainers.forEach(container => {
        let startX;
        container.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        });
        
        container.addEventListener('touchmove', (e) => {
            if (!startX) return;
            
            const currentX = e.touches[0].clientX;
            const diff = startX - currentX;
            
            if (Math.abs(diff) > 5) {
                // Prevent default scrolling when interacting with chart
                e.preventDefault();
            }
        });
    });

    // Handle table scrolling on mobile
    const tables = document.querySelectorAll('.table-container');
    tables.forEach(table => {
        if (table.scrollWidth > table.clientWidth) {
            showNotification('Swipe horizontally to view more data', 'info');
        }
    });
});
