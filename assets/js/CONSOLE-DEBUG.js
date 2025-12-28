// ============================================
// READER DEBUG SCRIPT
// Copy-paste dit in de browser console (F12)
// terwijl je op de reader pagina bent
// ============================================

console.log('🔍 Starting Reader Debug...');

// Test 1: Check if variables exist
console.log('📊 Current State:');
console.log('  currentBook:', typeof currentBook !== 'undefined' ? currentBook : 'NOT DEFINED');
console.log('  currentChapter:', typeof currentChapter !== 'undefined' ? currentChapter : 'NOT DEFINED');
console.log('  currentProfile:', typeof currentProfile !== 'undefined' ? currentProfile : 'NOT DEFINED');

// Test 2: Check dropdown values
const profileSelect = document.getElementById('profileSelect');
const bookSelect = document.getElementById('bookSelect');
const chapterSelect = document.getElementById('chapterSelect');

console.log('📋 Dropdown Values:');
console.log('  Profile dropdown:', profileSelect ? profileSelect.value : 'NOT FOUND');
console.log('  Book dropdown:', bookSelect ? bookSelect.value : 'NOT FOUND');
console.log('  Chapter dropdown:', chapterSelect ? chapterSelect.value : 'NOT FOUND');

// Test 3: Test API call manually
async function testAPI() {
    console.log('🧪 Testing API call...');
    
    const profiel = profileSelect ? profileSelect.value : '11';
    const boek = 'Ezra';
    const hoofdstuk = '1';
    
    const url = `?api=verses&boek=${boek}&hoofdstuk=${hoofdstuk}&profiel_id=${profiel}&limit=5`;
    console.log('  URL:', url);
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        console.log('✅ API Response:', data);
        
        let withOpmaak = 0;
        let withoutOpmaak = 0;
        
        data.forEach(v => {
            if (v.Opgemaakte_Tekst && v.Opgemaakte_Tekst.trim()) {
                withOpmaak++;
                console.log(`  ✅ Vers ${v.Versnummer} HAS opmaak`);
            } else {
                withoutOpmaak++;
                console.log(`  ❌ Vers ${v.Versnummer} NO opmaak`);
            }
        });
        
        console.log(`📊 Summary: ${withOpmaak} with opmaak, ${withoutOpmaak} without`);
        
    } catch (error) {
        console.error('❌ API Error:', error);
    }
}

// Test 4: Check if reader.js functions exist
console.log('🔧 Available Functions:');
console.log('  initReader:', typeof initReader !== 'undefined' ? '✅' : '❌');
console.log('  loadVerses:', typeof loadVerses !== 'undefined' ? '✅' : '❌');
console.log('  apiCall:', typeof apiCall !== 'undefined' ? '✅' : '❌');

// Run API test
console.log('\n🚀 Running API test...');
testAPI();

console.log('\n📝 Next Steps:');
console.log('1. Check if API test shows opmaak data');
console.log('2. If YES: Reader variables are wrong');
console.log('3. If NO: Database has no opmaak for this book/chapter');
console.log('\n✅ Debug complete! Check results above.');
