const crypto = require('crypto');
const fs = require('fs');

// Generate key pair
const { privateKey, publicKey } = crypto.generateKeyPairSync('rsa', {
    modulusLength: 2048,
    publicKeyEncoding: {
        type: 'spki',
        format: 'pem'
    },
    privateKeyEncoding: {
        type: 'pkcs8',
        format: 'pem'
    }
});

// Save to files
fs.writeFileSync('private.pem', privateKey);
fs.writeFileSync('public.pem', publicKey);

console.log('Keys generated successfully!');