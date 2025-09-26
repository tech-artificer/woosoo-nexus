import express from 'express';
import bodyParser from 'body-parser';
import fs from 'fs';
import { exec } from 'child_process';

const app = express();
app.use(bodyParser.urlencoded({ extended: true }));

app.post('/print', (req, res) => {
  const content = req.body.content || 'No content';
  fs.writeFileSync('/tmp/printjob.txt', content);

  exec(`lp /tmp/printjob.txt`, (err) => {
    if (err) return res.status(500).send("Print failed");
    res.send("Printed");
  });
});

app.listen(9100, () => console.log("Print service listening on 9100"));
