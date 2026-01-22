/**
 * MULTI-PROFILE INDICATOR
 * Toont welke verzen in andere profielen bewerkt zijn
 * Met klikbare tooltips om tussen profielen te wisselen
 */

(function() {
    'use strict';
    
    // Constants
    const CONFIG = {
        INIT_DELAY: 500,
        REPROCESS_DELAY: 300,
        CHECK_INTERVAL: 100,
        CHECK_TIMEOUT: 5000,
        TOOLTIP_CLOSE_DELAY: 100
    };
    
    // State
    const profileCache = new Map();
    const processedChapters = new Set();
    let allProfiles = [];
    
    /**
     * Parse chapter header text into boek and hoofdstuk
     * @param {string} headerText - Chapter header text
     * @returns {{boek: string, hoofdstuk: string}|null}
     */
    function parseChapterHeader(headerText) {
        const parts = headerText.trim().split(' ');
        if (parts.length < 2) {
            console.warn('⚠️ Invalid chapter header format:', headerText);
            return null;
        }
        
        return {
            hoofdstuk: parts[parts.length - 1],
            boek: parts.slice(0, -1).join(' ')
        };
    }
    
    /**
     * Load all profiles once
     */
    async function loadAllProfiles() {
        if (allProfiles.length > 0) return allProfiles;
        
        try {
            allProfiles = await apiCall('profiles');
            console.log(`📋 Loaded ${allProfiles.length} profiles for tooltips`);
            return allProfiles;
        } catch (error) {
            console.error('Error loading profiles:', error);
            return [];
        }
    }
    
    /**
     * Load profiles for a specific chapter
     */
    async function loadProfilesForChapter(boek, hoofdstuk) {
        const cacheKey = `${boek}_${hoofdstuk}`;
        
        if (profileCache.has(cacheKey)) {
            return profileCache.get(cacheKey);
        }
        
        try {
            const data = await apiCall(`chapter_profiles&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}`);
            profileCache.set(cacheKey, data || {});
            console.log(`📊 Loaded profiles for ${boek} ${hoofdstuk}: ${Object.keys(data || {}).length} verses`);
            return data || {};
        } catch (error) {
            console.error(`Error loading profiles for ${boek} ${hoofdstuk}:`, error);
            return {};
        }
    }
    
    /**
     * Switch to a different profile
     */
    function switchToProfile(profielId, profielNaam) {
        console.log(`🔄 Switching to profile: ${profielNaam} (ID: ${profielId})`);
        
        const profileSelect = document.getElementById('profileSelect');
        if (!profileSelect) {
            console.warn('⚠️ Profile selector not found');
            return;
        }
        
        profileSelect.value = profielId;
        
        // Trigger change event to reload verses
        const event = new Event('change', { bubbles: true });
        profileSelect.dispatchEvent(event);
        
        // Show notification
        if (typeof showNotification === 'function') {
            showNotification(`Profiel gewisseld naar: ${profielNaam}`, false);
        }
        
        // Close any open tooltips
        closeAllTooltips();
    }
    
    /**
     * Escape HTML for safety
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Create clickable tooltip HTML
     */
    function createTooltipHTML(profiles) {
        const items = profiles.map(p => {
            return `<span class="tooltip-profile-item" 
                          onclick="window.switchToProfile(${p.Profiel_ID}, '${escapeHtml(p.Profiel_Naam)}'); event.stopPropagation();"
                          title="Klik om naar dit profiel te wisselen">
                        ${escapeHtml(p.Profiel_Naam)}
                    </span>`;
        }).join('');
        
        return `<div class="tooltip-profile-list">
                    <div class="tooltip-header">Ook bewerkt in:</div>
                    ${items}
                </div>`;
    }
    
    /**
     * Show tooltip on hover
     */
    function showTooltip(versNumber) {
        const tooltipHTML = versNumber.getAttribute('data-tooltip-html');
        if (!tooltipHTML) return;
        
        // Remove any existing tooltip
        closeAllTooltips();
        
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'verse-profile-tooltip';
        tooltip.innerHTML = tooltipHTML;
        document.body.appendChild(tooltip);
        
        // Position tooltip
        const rect = versNumber.getBoundingClientRect();
        tooltip.style.position = 'fixed';
        tooltip.style.left = rect.left + 'px';
        tooltip.style.top = (rect.bottom + 5) + 'px';
        
        // Adjust if tooltip goes off-screen
        setTimeout(() => {
            const tooltipRect = tooltip.getBoundingClientRect();
            if (tooltipRect.right > window.innerWidth) {
                tooltip.style.left = (window.innerWidth - tooltipRect.width - 10) + 'px';
            }
            if (tooltipRect.bottom > window.innerHeight) {
                tooltip.style.top = (rect.top - tooltipRect.height - 5) + 'px';
            }
        }, 0);
        
        // Store reference for closing
        versNumber._activeTooltip = tooltip;
    }
    
    /**
     * Close all tooltips
     */
    function closeAllTooltips() {
        document.querySelectorAll('.verse-profile-tooltip').forEach(t => t.remove());
    }
    
    /**
     * Update verses in a chapter with profile indicators
     */
    function updateVersesInChapter(chapterElement, profileMappings) {
        const currentProfileId = (typeof currentProfile !== 'undefined' && currentProfile) 
            ? parseInt(currentProfile) 
            : null;
        
        let nextElement = chapterElement.nextElementSibling;
        let versesUpdated = 0;
        
        while (nextElement && !nextElement.classList.contains('chapter-header')) {
            if (nextElement.classList.contains('verse')) {
                const versNumber = nextElement.querySelector('.verse-number');
                
                if (versNumber) {
                    const versnummer = versNumber.textContent.trim();
                    const profiles = profileMappings[versnummer] || [];
                    
                    // Remove old event listeners by cloning
                    const oldClone = versNumber.cloneNode(true);
                    versNumber.parentNode.replaceChild(oldClone, versNumber);
                    const freshVersNumber = oldClone;
                    
                    if (profiles.length === 0) {
                        freshVersNumber.classList.remove('has-other-profiles');
                        freshVersNumber.removeAttribute('data-tooltip-html');
                        freshVersNumber.removeAttribute('title');
                    } else {
                        const otherProfiles = currentProfileId 
                            ? profiles.filter(p => p.Profiel_ID !== currentProfileId)
                            : profiles;
                        
                        if (otherProfiles.length > 0) {
                            freshVersNumber.classList.add('has-other-profiles');
                            
                            // Create HTML tooltip
                            const tooltipHTML = createTooltipHTML(otherProfiles);
                            freshVersNumber.setAttribute('data-tooltip-html', tooltipHTML);
                            
                            // Simple title for browsers without JS
                            const tooltipText = otherProfiles.map(p => p.Profiel_Naam).join(', ');
                            freshVersNumber.setAttribute('title', `Ook bewerkt in: ${tooltipText}`);
                            
                            // Add hover listeners
                            freshVersNumber.addEventListener('mouseenter', function() {
                                showTooltip(this);
                            });
                            
                            freshVersNumber.addEventListener('mouseleave', function() {
                                setTimeout(() => {
                                    if (this._activeTooltip && !this._activeTooltip.matches(':hover')) {
                                        closeAllTooltips();
                                    }
                                }, CONFIG.TOOLTIP_CLOSE_DELAY);
                            });
                            
                            versesUpdated++;
                        } else {
                            freshVersNumber.classList.remove('has-other-profiles');
                            freshVersNumber.removeAttribute('data-tooltip-html');
                            freshVersNumber.removeAttribute('title');
                        }
                    }
                }
            }
            
            nextElement = nextElement.nextElementSibling;
        }
        
        return versesUpdated;
    }
    
    /**
     * Process a single chapter header
     */
    async function processChapterHeader(chapterElement) {
        const headerText = chapterElement.textContent.trim();
        const parsed = parseChapterHeader(headerText);
        
        if (!parsed) return;
        
        const { boek, hoofdstuk } = parsed;
        const chapterKey = `${boek}_${hoofdstuk}`;
        
        if (processedChapters.has(chapterKey)) {
            return;
        }
        
        console.log(`🔍 Processing chapter: ${boek} ${hoofdstuk}`);
        processedChapters.add(chapterKey);
        
        const profileMappings = await loadProfilesForChapter(boek, hoofdstuk);
        const versesUpdated = updateVersesInChapter(chapterElement, profileMappings);
        
        console.log(`✅ Updated ${versesUpdated} verses in ${boek} ${hoofdstuk}`);
    }
    
    /**
     * Scan and process all chapters
     */
    async function scanAndProcessAllChapters() {
        const headers = document.querySelectorAll('.chapter-header');
        console.log(`🔎 Scanning ${headers.length} chapter headers...`);
        
        for (const header of headers) {
            await processChapterHeader(header);
        }
    }
    
    /**
     * Setup MutationObserver to detect new chapters
     */
    function setupMutationObserver() {
        const bibleText = document.getElementById('bibleText');
        
        if (!bibleText) {
            console.warn('⚠️ bibleText element not found');
            return;
        }
        
        const observer = new MutationObserver((mutations) => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.classList && node.classList.contains('chapter-header')) {
                        console.log('🆕 New chapter detected via MutationObserver!');
                        processChapterHeader(node);
                    }
                });
            });
        });
        
        observer.observe(bibleText, {
            childList: true,
            subtree: false
        });
        
        console.log('👀 MutationObserver active - watching for new chapters');
    }
    
    /**
     * Reprocess all verses with cached data
     */
    function reprocessAllVerses() {
        console.log('🔄 Reprocessing all verses...');
        
        const headers = document.querySelectorAll('.chapter-header');
        
        headers.forEach(header => {
            const parsed = parseChapterHeader(header.textContent.trim());
            if (parsed) {
                const { boek, hoofdstuk } = parsed;
                const cacheKey = `${boek}_${hoofdstuk}`;
                const profileMappings = profileCache.get(cacheKey) || {};
                updateVersesInChapter(header, profileMappings);
            }
        });
    }
    
    /**
     * Initialize the multi-profile indicator
     */
    function init() {
        console.log('🔵 Multi-profiel indicator V4 (CLICKABLE) initializing...');
        console.log('   ✓ Auto-detect nieuwe hoofdstukken');
        console.log('   ✓ Onbeperkt scrollen support');
        console.log('   ✓ Werkt bij eerste load');
        console.log('   ✓ 🆕 KLIKBARE PROFIELEN IN TOOLTIP!');
        
        // Load profiles first
        loadAllProfiles();
        
        // Wait for apiCall to be available
        const checkInterval = setInterval(function() {
            if (typeof apiCall !== 'undefined') {
                clearInterval(checkInterval);
                
                console.log('✅ Multi-profiel indicator V4 ready!');
                
                setupMutationObserver();
                
                setTimeout(() => {
                    scanAndProcessAllChapters();
                }, CONFIG.INIT_DELAY);
                
                const profileSelect = document.getElementById('profileSelect');
                if (profileSelect) {
                    profileSelect.addEventListener('change', function() {
                        console.log('👤 Profile changed - reprocessing...');
                        setTimeout(() => reprocessAllVerses(), CONFIG.REPROCESS_DELAY);
                    });
                }
            }
        }, CONFIG.CHECK_INTERVAL);
        
        setTimeout(() => clearInterval(checkInterval), CONFIG.CHECK_TIMEOUT);
    }
    
    // Close tooltips when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.verse-profile-tooltip') && !e.target.closest('.has-other-profiles')) {
            closeAllTooltips();
        }
    });
    
    // Make functions globally available
    window.switchToProfile = switchToProfile;
    window.closeAllTooltips = closeAllTooltips;
    
    window.multiProfielDebug = {
        cache: profileCache,
        processed: processedChapters,
        scan: scanAndProcessAllChapters,
        reprocess: reprocessAllVerses
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
