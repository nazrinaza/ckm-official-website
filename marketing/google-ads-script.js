/**
 * CKM — Google Ads Script: Performance Monitor & Auto-Optimize
 * 
 * CARA GUNA:
 * 1. Log masuk Google Ads → Tools & Settings → Bulk Actions → Scripts
 * 2. Klik "+" → Tambah script baru
 * 3. Copy & paste seluruh kod ini
 * 4. Klik "Preview" untuk test (run sekali tanpa perubahan)
 * 5. Bila OK, klik "Save" → enable schedule (setiap hari 8 pagi)
 * 6. Klik "Authorize" untuk benarkan script akses account
 * 
 * APA SCRIPT NI BUAT:
 * - Pause keywords dengan CTR < 1% (lebih 30 hari) — buang bazaya
 * - Pause keywords dengan CPA > RM50 (lebih 7 hari) — kawal kos
 * - Naikkan bid 20% untuk keywords dengan CPA < RM20 (performer terbaik)
 * - Email laporan harian ke bell@cucikarpetmasjid.com
 * - Log semua perubahan untuk audit
 */

// ═══ KONFIGURASI — TUKAR NILAI DI BAWAH ═══
var CONFIG = {
  EMAIL: 'bell@cucikarpetmasjid.com',        // Email untuk laporan harian
  LOW_CTR_THRESHOLD: 0.01,                    // Pause keyword jika CTR < 1% (30 hari)
  HIGH_CPA_THRESHOLD: 50,                     // Pause keyword jika CPA > RM50 (7 hari)  
  GOOD_CPA_THRESHOLD: 20,                     // Naik bid jika CPA < RM20
  BID_BOOST_PERCENT: 1.20,                    // Naik bid 20% untuk performer terbaik
  BID_REDUCE_PERCENT: 0.85,                   // Turun bid 15% untuk CPA tinggi
  LOOKBACK_DAYS: 30,                          // Analisa 30 hari lepas
  MIN_IMPRESSIONS: 50,                        // Minim impression sebelum tindakan
  DRY_RUN: false,                              // true = log sahaja, false = buat perubahan
};
// ═══ TAMAT KONFIGURASI ═══

function main() {
  Logger.log('═══ CKM Google Ads Monitor — ' + new Date() + ' ═══');
  Logger.log('Mode: ' + (CONFIG.DRY_RUN ? 'DRY RUN (preview)' : 'LIVE'));
  Logger.log('');
  
  var report = [];
  report.push('═══ LAPORAN HARIAN CKM GOOGLE ADS ═══');
  report.push('Tarikh: ' + new Date());
  report.push('Mode: ' + (CONFIG.DRY_RUN ? 'DRY RUN' : 'LIVE'));
  report.push('');
  
  var changes = [];
  
  // 1. Pause low-CTR keywords
  Logger.log('▶ Mencari keywords dengan CTR rendah...');
  var lowCtr = findLowCtrKeywords();
  report.push('── Keywords CTR Rendah (<' + (CONFIG.LOW_CTR_THRESHOLD * 100) + '%) ──');
  if (lowCtr.length === 0) {
    report.push('Tiada. Semua keywords CTR OK.');
  } else {
    lowCtr.forEach(function(k) {
      var msg = '  ⚠ PAUSE: "' + k.text + '" — CTR: ' + (k.ctr * 100).toFixed(2) + '%, Imp: ' + k.impressions;
      Logger.log(msg);
      report.push(msg);
      changes.push({ action: 'pause', keyword: k.text, reason: 'CTR < 1%' });
      if (!CONFIG.DRY_RUN) {
        k.keyword.pause();
      }
    });
  }
  report.push('');
  
  // 2. Pause high-CPA keywords
  Logger.log('▶ Mencari keywords dengan CPA tinggi...');
  var highCpa = findHighCpaKeywords();
  report.push('── Keywords CPA Tinggi (>RM' + CONFIG.HIGH_CPA_THRESHOLD + ') ──');
  if (highCpa.length === 0) {
    report.push('Tiada. Semua keywords CPA dalam bajet.');
  } else {
    highCpa.forEach(function(k) {
      var msg = '  ⚠ PAUSE: "' + k.text + '" — CPA: RM' + k.cpa.toFixed(2) + ', Cost: RM' + k.cost.toFixed(2);
      Logger.log(msg);
      report.push(msg);
      changes.push({ action: 'pause', keyword: k.text, reason: 'CPA > RM50' });
      if (!CONFIG.DRY_RUN) {
        k.keyword.pause();
      }
    });
  }
  report.push('');
  
  // 3. Boost low-CPA keywords
  Logger.log('▶ Mencari keywords performer terbaik...');
  var goodCpa = findGoodCpaKeywords();
  report.push('── Keywords Performer Terbaik (CPA <RM' + CONFIG.GOOD_CPA_THRESHOLD + ') ──');
  if (goodCpa.length === 0) {
    report.push('Tiada keyword yang layak untuk boost.');
  } else {
    goodCpa.forEach(function(k) {
      var oldBid = k.keyword.bidding().getCpc();
      var newBid = oldBid * CONFIG.BID_BOOST_PERCENT;
      var msg = '  ✓ BOOST: "' + k.text + '" — CPA: RM' + k.cpa.toFixed(2) + ', Bid: RM' + oldBid.toFixed(2) + ' → RM' + newBid.toFixed(2);
      Logger.log(msg);
      report.push(msg);
      changes.push({ action: 'boost', keyword: k.text, reason: 'CPA < RM20', oldBid: oldBid, newBid: newBid });
      if (!CONFIG.DRY_RUN) {
        k.keyword.bidding().setCpc(newBid);
      }
    });
  }
  report.push('');
  
  // 4. Account summary
  Logger.log('▶ Mengumpulkan summary account...');
  var summary = getAccountSummary();
  report.push('── RINGKASAN ACCOUNT ──');
  report.push('  Clicks: ' + summary.clicks);
  report.push('  Impressions: ' + summary.impressions);
  report.push('  CTR: ' + (summary.ctr * 100).toFixed(2) + '%');
  report.push('  Cost: RM' + summary.cost.toFixed(2));
  report.push('  Conversions: ' + summary.conversions);
  report.push('  CPA: RM' + (summary.conversions > 0 ? (summary.cost / summary.conversions).toFixed(2) : '0.00'));
  report.push('  Cost/Conversion: RM' + (summary.conversions > 0 ? (summary.cost / summary.conversions).toFixed(2) : '0.00'));
  report.push('');
  
  // 5. Changes summary
  report.push('── RINGKASAN TINDAKAN ──');
  report.push('  Total perubahan: ' + changes.length);
  report.push('  Keywords dipause: ' + changes.filter(function(c) { return c.action === 'pause'; }).length);
  report.push('  Keywords di-boost: ' + changes.filter(function(c) { return c.action === 'boost'; }).length);
  report.push('');
  
  if (CONFIG.DRY_RUN) {
    report.push('⚠ MODE DRY RUN — Tiada perubahan dibuat. Tukar DRY_RUN ke false untuk aktif.');
  } else {
    report.push('✓ Semua perubahan telah dilaksanakan.');
  }
  
  Logger.log('');
  Logger.log('═══ SELESAI ═══');
  
  // 6. Send email report
  sendEmailReport(report.join('\n'));
}

function findLowCtrKeywords() {
  var results = [];
  var query = "SELECT Keyword, Clicks, Impressions, Ctr, CampaignName, AdGroupName " +
    "FROM KEYWORDS_PERFORMANCE_REPORT " +
    "WHERE Impressions > " + CONFIG.MIN_IMPRESSIONS + " " +
    "AND Ctr < " + CONFIG.LOW_CTR_THRESHOLD + " " +
    "AND CampaignStatus = ENABLED " +
    "AND AdGroupStatus = ENABLED " +
    "AND KeywordMatchType != EXACT " +
    "DURING LAST_" + CONFIG.LOOKBACK_DAYS + "_DAYS";
  
  var report = AdsApp.report(query);
  report.rows().forEach(function(row) {
    var keywordText = row['Keyword'];
    var ctr = parseFloat(row['Ctr'].replace('%', '')) / 100;
    var impressions = parseInt(row['Impressions']);
    var campaignName = row['CampaignName'];
    var adGroupName = row['AdGroupName'];
    
    // Find actual keyword object
    var kwIterator = AdsApp.keywords()
      .withCondition('Text = "' + keywordText + '"')
      .withCondition('CampaignName = "' + campaignName + '"')
      .withCondition('AdGroupName = "' + adGroupName + '"')
      .get();
    
    if (kwIterator.hasNext()) {
      results.push({
        keyword: kwIterator.next(),
        text: keywordText,
        ctr: ctr,
        impressions: impressions
      });
    }
  });
  
  return results;
}

function findHighCpaKeywords() {
  var results = [];
  var query = "SELECT Keyword, Cost, Conversions, CampaignName, AdGroupName " +
    "FROM KEYWORDS_PERFORMANCE_REPORT " +
    "WHERE Conversions > 0 " +
    "AND CampaignStatus = ENABLED " +
    "AND AdGroupStatus = ENABLED " +
    "DURING LAST_7_DAYS";
  
  var report = AdsApp.report(query);
  report.rows().forEach(function(row) {
    var keywordText = row['Keyword'];
    var cost = parseFloat(row['Cost'].replace(',', ''));
    var conversions = parseInt(row['Conversions']);
    var cpa = cost / conversions;
    var campaignName = row['CampaignName'];
    var adGroupName = row['AdGroupName'];
    
    if (cpa > CONFIG.HIGH_CPA_THRESHOLD) {
      var kwIterator = AdsApp.keywords()
        .withCondition('Text = "' + keywordText + '"')
        .withCondition('CampaignName = "' + campaignName + '"')
        .withCondition('AdGroupName = "' + adGroupName + '"')
        .get();
      
      if (kwIterator.hasNext()) {
        results.push({
          keyword: kwIterator.next(),
          text: keywordText,
          cpa: cpa,
          cost: cost,
          conversions: conversions
        });
      }
    }
  });
  
  return results;
}

function findGoodCpaKeywords() {
  var results = [];
  var query = "SELECT Keyword, Cost, Conversions, CampaignName, AdGroupName " +
    "FROM KEYWORDS_PERFORMANCE_REPORT " +
    "WHERE Conversions >= 2 " +
    "AND CampaignStatus = ENABLED " +
    "AND AdGroupStatus = ENABLED " +
    "DURING LAST_7_DAYS";
  
  var report = AdsApp.report(query);
  report.rows().forEach(function(row) {
    var keywordText = row['Keyword'];
    var cost = parseFloat(row['Cost'].replace(',', ''));
    var conversions = parseInt(row['Conversions']);
    var cpa = cost / conversions;
    var campaignName = row['CampaignName'];
    var adGroupName = row['AdGroupName'];
    
    if (cpa > 0 && cpa < CONFIG.GOOD_CPA_THRESHOLD) {
      var kwIterator = AdsApp.keywords()
        .withCondition('Text = "' + keywordText + '"')
        .withCondition('CampaignName = "' + campaignName + '"')
        .withCondition('AdGroupName = "' + adGroupName + '"')
        .get();
      
      if (kwIterator.hasNext()) {
        results.push({
          keyword: kwIterator.next(),
          text: keywordText,
          cpa: cpa,
          cost: cost,
          conversions: conversions
        });
      }
    }
  });
  
  return results;
}

function getAccountSummary() {
  var query = "SELECT Clicks, Impressions, Ctr, Cost, Conversions " +
    "FROM ACCOUNT_PERFORMANCE_REPORT " +
    "DURING LAST_7_DAYS";
  
  var report = AdsApp.report(query);
  var row = report.rows().next();
  
  return {
    clicks: parseInt(row['Clicks']),
    impressions: parseInt(row['Impressions']),
    ctr: parseFloat(row['Ctr'].replace('%', '')) / 100,
    cost: parseFloat(row['Cost'].replace(',', '')),
    conversions: parseInt(row['Conversions'])
  };
}

function sendEmailReport(body) {
  if (!CONFIG.EMAIL) return;
  
  var subject = 'CKM Google Ads — Laporan Harian ' + Utilities.formatDate(new Date(), 'Asia/Kuala_Lumpur', 'dd/MM/yyyy');
  
  MailApp.sendEmail({
    to: CONFIG.EMAIL,
    subject: subject,
    body: body
  });
  
  Logger.log('Laporan dihantar ke ' + CONFIG.EMAIL);
}
