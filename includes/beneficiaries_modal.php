<!-- beneficiaries_modal.php -->
<div id="beneficiaries-modal" class="modal-overlay">
    <div class="modal-box" style="max-width: 800px;">
        <span class="close-modal" onclick="closeModal('beneficiaries-modal')">×</span>
        <h2>Manage Beneficiaries</h2>
        
        <div id="beneficiary-rows" style="margin-bottom: 20px;"></div>
        
        <button type="button" onclick="addBeneficiaryRow()" 
                style="margin-bottom: 16px; padding: 12px 20px; background: #c8102e; color: white; border: none; border-radius: 6px; cursor: pointer;">
            + Add Beneficiary Type
        </button>

        <div class="modal-actions" style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
            <button onclick="saveBeneficiaries()" 
                    style="padding: 12px 24px; background: #c8102e; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                Save
            </button>
            <button onclick="closeModal('beneficiaries-modal')" 
                    style="padding: 12px 24px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
// Global array to hold beneficiary data
let beneficiariesData = [];

// Load pre-filled data from hidden input or parent
function loadBeneficiaries(preFilledJson = '[]') {
    try {
        beneficiariesData = JSON.parse(preFilledJson);
    } catch (e) {
        beneficiariesData = [];
        console.error('Invalid beneficiaries JSON:', preFilledJson);
    }
    
    const rowsDiv = document.getElementById('beneficiary-rows');
    rowsDiv.innerHTML = '';

    if (beneficiariesData.length === 0) {
        rowsDiv.innerHTML = '<p style="color:#6b7280;">No beneficiaries added yet. Click "+ Add Beneficiary Type" to begin.</p>';
    } else {
        beneficiariesData.forEach((b, index) => {
            addBeneficiaryRow(b.type || '', b.male || 0, b.female || 0, index);
        });
    }
}

// Add a new row (with optional pre-filled values)
function addBeneficiaryRow(type = '', male = 0, female = 0, index = null) {
    const rowsDiv = document.getElementById('beneficiary-rows');
    const rowId = index !== null ? index : beneficiariesData.length;

    const row = document.createElement('div');
    row.style.display = 'flex';
    row.style.gap = '12px';
    row.style.alignItems = 'center';
    row.style.marginBottom = '12px';
    row.style.padding = '8px';
    row.style.border = '1px solid #d1d5db';
    row.style.borderRadius = '6px';
    row.innerHTML = `
        <input type="text" placeholder="Beneficiary Type (e.g. Students)" 
               value="${type}" style="flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" 
               onchange="updateBeneficiary(${rowId}, 'type', this.value)">
        
        <input type="number" placeholder="Male" min="0" value="${male}" 
               style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" 
               onchange="updateBeneficiary(${rowId}, 'male', this.value)">
        
        <input type="number" placeholder="Female" min="0" value="${female}" 
               style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" 
               onchange="updateBeneficiary(${rowId}, 'female', this.value)">
        
        <button type="button" onclick="removeBeneficiaryRow(${rowId})" 
                style="background: #dc2626; color: white; border: none; border-radius: 4px; padding: 8px 12px; cursor: pointer;">
            Remove
        </button>
    `;

    rowsDiv.appendChild(row);

    // If new row, add to data array
    if (index === null) {
        beneficiariesData.push({ type: '', male: 0, female: 0 });
    }
}

// Update data when input changes
function updateBeneficiary(index, field, value) {
    if (beneficiariesData[index]) {
        beneficiariesData[index][field] = field === 'type' ? value : Number(value) || 0;
    }
}

// Remove row
function removeBeneficiaryRow(index) {
    beneficiariesData.splice(index, 1);
    // Re-render all rows to fix indices
    const rowsDiv = document.getElementById('beneficiary-rows');
    rowsDiv.innerHTML = '';
    beneficiariesData.forEach((b, i) => {
        addBeneficiaryRow(b.type, b.male, b.female, i);
    });
}

// Save to hidden input as JSON
function saveBeneficiaries() {
    const hidden = document.getElementById('beneficiaries-hidden');
    if (hidden) {
        hidden.value = JSON.stringify(beneficiariesData.filter(b => b.type.trim() !== ''));
        // Optional: update preview div
        const preview = document.getElementById('selected-beneficiaries');
        if (preview) {
            const count = beneficiariesData.filter(b => b.type.trim() !== '').length;
            preview.textContent = count > 0 ? `${count} beneficiary type(s) configured` : 'None selected';
        }
    }
    closeModal('beneficiaries-modal');
}

// Initialize when modal opens
function openBeneficiariesModal() {
    openModal('beneficiaries-modal');
    const preFilled = document.getElementById('beneficiaries-hidden')?.value || '[]';
    loadBeneficiaries(preFilled);
}
</script>