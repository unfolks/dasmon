Body
{
  "jsonrpc": "2.0",
  "method": "hostgroup.get",
  "params": { "output": ["groupid", "name"] },
  "id": 1
}

Response 
{
  "jsonrpc": "2.0",
  "result": [
    {
      "groupid": "23",
      "name": "Krian/Access Point"
    },
    {
      "groupid": "26",
      "name": "Krian/CCTV"
    },
    {
      "groupid": "25",
      "name": "Krian/Computers"
    },
    {
      "groupid": "24",
      "name": "Krian/Network"
    }
  ],
  "id": 1
}

Body
{
  "jsonrpc": "2.0",
  "method": "item.get",
  "params": {
    "groupids": ["23"],
    "monitored": true,
    "filter": { "key_": ["icmpping", "icmppingsec", "icmppingloss"] },
    "output": ["hostid", "key_", "lastvalue"],
    "selectHosts": ["hostid", "name"]
  },
  "id": 1
}
Response 
{
  "jsonrpc": "2.0",
  "result": [
    {
      "itemid": "53127",
      "hostid": "10691",
      "key_": "icmpping",
      "hosts": [
        {
          "hostid": "10691",
          "name": "Ruijie-AR-1-2"
        }
      ],
      "lastvalue": "1"
    },
    {
      "itemid": "53128",
      "hostid": "10691",
      "key_": "icmppingloss",
      "hosts": [
        {
          "hostid": "10691",
          "name": "Ruijie-AR-1-2"
        }
      ],
      "lastvalue": "0"
    },
    {
      "itemid": "53129",
      "hostid": "10691",
      "key_": "icmppingsec",
      "hosts": [
        {
          "hostid": "10691",
          "name": "Ruijie-AR-1-2"
        }
      ],
      "lastvalue": "0.0007516666666666667"
    },
    {
      "itemid": "53233",
      "hostid": "10692",
      "key_": "icmpping",
      "hosts": [
        {
          "hostid": "10692",
          "name": "Ruijie-AR-4"
        }
      ],
      "lastvalue": "1"
    },
    {
      "itemid": "53234",
      "hostid": "10692",
      "key_": "icmppingloss",
      "hosts": [
        {
          "hostid": "10692",
          "name": "Ruijie-AR-4"
        }
      ],
      "lastvalue": "0"
    },
    {
      "itemid": "53235",
      "hostid": "10692",
      "key_": "icmppingsec",
      "hosts": [
        {
          "hostid": "10692",
          "name": "Ruijie-AR-4"
        }
      ],
      "lastvalue": "0.0007523333333333333"
    },
    {
      "itemid": "53330",
      "hostid": "10693",
      "key_": "icmpping",
      "hosts": [
        {
          "hostid": "10693",
          "name": "Ruijie-AR-3"
        }
      ],
      "lastvalue": "1"
    },
    {
      "itemid": "53331",
      "hostid": "10693",
      "key_": "icmppingloss",
      "hosts": [
        {
          "hostid": "10693",
          "name": "Ruijie-AR-3"
        }
      ],
      "lastvalue": "0"
    },
    {
      "itemid": "53332",
      "hostid": "10693",
      "key_": "icmppingsec",
      "hosts": [
        {
          "hostid": "10693",
          "name": "Ruijie-AR-3"
        }
      ],
      "lastvalue": "0.00071"
    },
    {
      "itemid": "53436",
      "hostid": "10694",
      "key_": "icmpping",
      "hosts": [
        {
          "hostid": "10694",
          "name": "Ruijie-Loading-1"
        }
      ],
      "lastvalue": "1"
    },
    {
      "itemid": "53437",
      "hostid": "10694",
      "key_": "icmppingloss",
      "hosts": [
        {
          "hostid": "10694",
          "name": "Ruijie-Loading-1"
        }
      ],
      "lastvalue": "0"
    },
    {
      "itemid": "53438",
      "hostid": "10694",
      "key_": "icmppingsec",
      "hosts": [
        {
          "hostid": "10694",
          "name": "Ruijie-Loading-1"
        }
      ],
      "lastvalue": "0.0005540000000000001"
    },
    {
      "itemid": "53601",
      "hostid": "10696",
      "key_": "icmpping",
      "hosts": [
        {
          "hostid": "10696",
          "name": "Ruijie-CS-1-15"
        }
      ],
      "lastvalue": "1"
    },
    {
      "itemid": "53602",
      "hostid": "10696",
      "key_": "icmppingloss",
      "hosts": [
        {
          "hostid": "10696",
          "name": "Ruijie-CS-1-15"
        }
      ],
      "lastvalue": "0"
    },
    {
      "itemid": "53603",
      "hostid": "10696",
      "key_": "icmppingsec",
      "hosts": [
        {
          "hostid": "10696",
          "name": "Ruijie-CS-1-15"
        }
      ],
      "lastvalue": "0.0009130000000000001"
    },
    {
      "itemid": "54187",
      "hostid": "10702",
      "key_": "icmpping",
      "hosts": [
        {
          "hostid": "10702",
          "name": "Ruijie-Office-Lt-1"
        }
      ],
      "lastvalue": "1"
    },
    {
      "itemid": "54188",
      "hostid": "10702",
      "key_": "icmppingloss",
      "hosts": [
        {
          "hostid": "10702",
          "name": "Ruijie-Office-Lt-1"
        }
      ],
      "lastvalue": "0"
    },
    {
      "itemid": "54189",
      "hostid": "10702",
      "key_": "icmppingsec",
      "hosts": [
        {
          "hostid": "10702",
          "name": "Ruijie-Office-Lt-1"
        }
      ],
      "lastvalue": "0.000554"
    }
  ],
  "id": 1
}
