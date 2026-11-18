export const sandboxAddress = {
    firstName: 'Sandbox', lastName: 'Buyer', phone: '+12025550124',
    address1: '123 Test Street', city: 'San Jose', province: 'CA', zip: '95131',
};

export async function fillMerchDelivery(checkout, email) {
    const variant = checkout.locator('[name="variant_id"]');
    await variant.selectOption({ index: 0 });
    await checkout.locator('[name="country"]').selectOption('US');
    for (const [name, value] of Object.entries({ ...sandboxAddress, email })) {
        await checkout.locator(`input[name="${name}"]`).fill(value);
    }
    return variant.locator('option:checked').innerText();
}
