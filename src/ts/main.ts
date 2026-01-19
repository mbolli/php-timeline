// Timeline Application - Main Entry
// Handles zoom, pan, and timeline interactions

interface TimelineState {
    zoom: number;
    panX: number;
    isDragging: boolean;
    lastMouseX: number;
}

class TimelineController {
    private state: TimelineState = {
        zoom: 1,
        panX: 0,
        isDragging: false,
        lastMouseX: 0,
    };

    private container: HTMLElement | null = null;
    private content: HTMLElement | null = null;

    constructor() {
        this.init();
    }

    private init(): void {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    private setup(): void {
        this.container = document.querySelector('.timeline-wrapper');
        this.content = document.querySelector('.timeline-content');

        if (!this.container || !this.content) {
            console.warn('Timeline elements not found, will retry on mutation');
            this.observeDOM();
            return;
        }

        this.bindEvents();
        this.applyZoom();
    }

    private observeDOM(): void {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.type === 'childList') {
                    this.container = document.querySelector('.timeline-wrapper');
                    this.content = document.querySelector('.timeline-content');
                    
                    if (this.container && this.content) {
                        observer.disconnect();
                        this.bindEvents();
                        this.applyZoom();
                        break;
                    }
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    private bindEvents(): void {
        if (!this.container) return;

        // Mouse wheel for zoom
        this.container.addEventListener('wheel', this.handleWheel.bind(this), { passive: false });

        // Mouse drag for pan
        this.container.addEventListener('mousedown', this.handleMouseDown.bind(this));
        document.addEventListener('mousemove', this.handleMouseMove.bind(this));
        document.addEventListener('mouseup', this.handleMouseUp.bind(this));

        // Touch events for mobile
        this.container.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });
        this.container.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
        this.container.addEventListener('touchend', this.handleTouchEnd.bind(this));

        // Keyboard navigation
        document.addEventListener('keydown', this.handleKeydown.bind(this));

        // Listen for Datastar signal changes
        this.observeSignals();
    }

    private observeSignals(): void {
        // Watch for zoom/panX signal changes from Datastar
        const observer = new MutationObserver(() => {
            const app = document.getElementById('app');
            if (!app) return;

            // Read signals from Datastar's data attributes
            const signalsAttr = app.getAttribute('data-signals');
            if (signalsAttr) {
                try {
                    // Note: This is a simplified approach
                    // In production, you'd integrate more deeply with Datastar's signal system
                } catch (e) {
                    // Ignore parsing errors
                }
            }
        });

        const app = document.getElementById('app');
        if (app) {
            observer.observe(app, { attributes: true });
        }
    }

    private handleWheel(e: WheelEvent): void {
        // Only zoom if Ctrl/Cmd is held, otherwise allow normal scroll
        if (!e.ctrlKey && !e.metaKey) {
            return;
        }

        e.preventDefault();

        const delta = e.deltaY > 0 ? 0.9 : 1.1;
        const newZoom = Math.max(0.1, Math.min(10, this.state.zoom * delta));

        // Zoom towards mouse position
        if (this.container) {
            const rect = this.container.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const zoomRatio = newZoom / this.state.zoom;
            
            this.state.panX = mouseX - (mouseX - this.state.panX) * zoomRatio;
        }

        this.state.zoom = newZoom;
        this.applyZoom();
        this.syncToDatastar();
    }

    private handleMouseDown(e: MouseEvent): void {
        // Ignore if clicking on an item (let Datastar handle it)
        if ((e.target as HTMLElement).closest('.timeline-item')) {
            return;
        }

        this.state.isDragging = true;
        this.state.lastMouseX = e.clientX;
        
        if (this.container) {
            this.container.style.cursor = 'grabbing';
        }
    }

    private handleMouseMove(e: MouseEvent): void {
        if (!this.state.isDragging) return;

        const deltaX = e.clientX - this.state.lastMouseX;
        this.state.panX += deltaX;
        this.state.lastMouseX = e.clientX;

        this.applyPan();
    }

    private handleMouseUp(): void {
        this.state.isDragging = false;
        
        if (this.container) {
            this.container.style.cursor = 'grab';
        }

        this.syncToDatastar();
    }

    private handleTouchStart(e: TouchEvent): void {
        if (e.touches.length === 1) {
            this.state.isDragging = true;
            this.state.lastMouseX = e.touches[0].clientX;
        }
    }

    private handleTouchMove(e: TouchEvent): void {
        if (!this.state.isDragging || e.touches.length !== 1) return;

        e.preventDefault();
        
        const deltaX = e.touches[0].clientX - this.state.lastMouseX;
        this.state.panX += deltaX;
        this.state.lastMouseX = e.touches[0].clientX;

        this.applyPan();
    }

    private handleTouchEnd(): void {
        this.state.isDragging = false;
        this.syncToDatastar();
    }

    private handleKeydown(e: KeyboardEvent): void {
        // Arrow keys for pan
        switch (e.key) {
            case 'ArrowLeft':
                this.state.panX += 50;
                break;
            case 'ArrowRight':
                this.state.panX -= 50;
                break;
            case '+':
            case '=':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    this.state.zoom = Math.min(10, this.state.zoom * 1.2);
                }
                break;
            case '-':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    this.state.zoom = Math.max(0.1, this.state.zoom * 0.8);
                }
                break;
            case '0':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    this.state.zoom = 1;
                    this.state.panX = 0;
                }
                break;
            default:
                return;
        }

        this.applyZoom();
        this.applyPan();
        this.syncToDatastar();
    }

    private applyZoom(): void {
        if (!this.content) return;

        // Scale the content
        this.content.style.transform = `scaleX(${this.state.zoom}) translateX(${this.state.panX / this.state.zoom}px)`;
        this.content.style.transformOrigin = 'left top';

        // Update CSS custom property for month width calculations
        document.documentElement.style.setProperty('--zoom-level', String(this.state.zoom));
    }

    private applyPan(): void {
        if (!this.content) return;

        this.content.style.transform = `scaleX(${this.state.zoom}) translateX(${this.state.panX / this.state.zoom}px)`;
    }

    private syncToDatastar(): void {
        // Update Datastar signals by dispatching custom events
        // This keeps the UI state in sync with Datastar's reactive system
        const app = document.getElementById('app');
        if (!app) return;

        // Create a custom event that Datastar can pick up
        const event = new CustomEvent('timeline:statechange', {
            detail: {
                zoom: this.state.zoom,
                panX: this.state.panX,
            },
        });
        app.dispatchEvent(event);
    }

    // Public methods for external control
    public setZoom(zoom: number): void {
        this.state.zoom = Math.max(0.1, Math.min(10, zoom));
        this.applyZoom();
    }

    public setPan(panX: number): void {
        this.state.panX = panX;
        this.applyPan();
    }

    public resetView(): void {
        this.state.zoom = 1;
        this.state.panX = 0;
        this.applyZoom();
    }

    public scrollToDate(dateStr: string): void {
        // dateStr format: 'YYYY-MM'
        const [year, month] = dateStr.split('-').map(Number);
        const startYear = this.getStartYear();
        const monthWidth = 20 * this.state.zoom;
        const monthsFromStart = ((year - startYear) * 12) + (month - 1);
        
        if (this.container) {
            const containerWidth = this.container.clientWidth;
            this.state.panX = -(monthsFromStart * monthWidth) + (containerWidth / 2);
            this.applyPan();
        }
    }

    private getStartYear(): number {
        const yearMarker = document.querySelector('.year-marker');
        if (yearMarker) {
            return parseInt(yearMarker.getAttribute('data-year') || '2000', 10);
        }
        return 2000;
    }
}

// Initialize timeline controller
const timeline = new TimelineController();

// Expose to window for debugging
declare global {
    interface Window {
        timeline: TimelineController;
    }
}
window.timeline = timeline;

// Export for module use
export { TimelineController };
