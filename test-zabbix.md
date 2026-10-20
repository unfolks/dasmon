PS D:\dashboard-monitor> $token = "ee92fab468bb05b542956433c3fc28338b780fb87d6fb211fd7ef8f3a84596a7"
>> $url   = "http://192.168.88.20:8080/api_jsonrpc.php"
>> 
>> $body = @{
>>     jsonrpc = "2.0"
>>     method  = "host.get"
>>     params  = @{
>>         output           = @("hostid", "name")
>>         selectHostGroups = @("name")
>>         selectInterfaces = @("ip")
>>     }
>>     id = 1
>> } | ConvertTo-Json -Depth 6
>> 
>> $res = Invoke-RestMethod -Uri $url -Method Post -ContentType "application/json-rpc" `
>>     -Headers @{ Authorization = "Bearer $token" } -Body $body
>> 
>> $res.result | ForEach-Object {
>>     [pscustomobject]@{
>>         hostid = $_.hostid
>>         name   = $_.name
>>         ip     = $_.interfaces[0].ip
>>         groups = ($_.hostgroups.name -join ", ")
>>     }
>> } | Format-Table -AutoSize

hostid name                       ip             groups        
------ ----                       --             ------        
10084  Zabbix server              127.0.0.1      Zabbix servers
10683  Mikrotik-CCR2116           192.168.25.1   Zabbix servers
10684  Mikrotik-CRS328-24P-4S-RM  192.168.35.7   Zabbix servers
10685  Mikrotik-CRS326-24G-2S-RM  192.168.35.5   Zabbix servers
10686  Mikrotik-CRS326-24S-2Q-RM  192.168.35.6   Zabbix servers
10687  Mikrotik-CSS-326-24G-2S-11 192.168.35.11  Zabbix servers
10688  Mikrotik-CSS-326-24G-2S-10 192.168.35.10  Zabbix servers
10689  Mikrotik-CSS-326-24G-2S-9  192.168.35.9   Zabbix servers
10690  Mikrotik-CSS-326-24G-2S-8  192.168.35.8   Zabbix servers
10691  Ruijie-AR-1-2              192.168.25.36  Zabbix servers
10692  Ruijie-AR-4                192.168.25.14  Zabbix servers
10693  Ruijie-AR-3                192.168.25.22  Zabbix servers
10694  Ruijie-Loading-1           192.168.25.62  Zabbix servers
10695  Ruijie-Office-Lt-1         192.168.30.83  Zabbix servers
10696  Ruijie-CS-1-15             192.168.25.25  Zabbix servers
10700  CCTV                       192.168.10.245 Zabbix servers


PS D:\dashboard-monitor> $token = "ee92fab468bb05b542956433c3fc28338b780fb87d6fb211fd7ef8f3a84596a7"
>> $url   = "http://192.168.88.20:8080/api_jsonrpc.php"
>> 
>> foreach ($id in @("10700","10691")) {
>>   $body = @{
>>     jsonrpc = "2.0"; method = "item.get"; id = 1
>>     params  = @{ hostids = @($id); output = @("name","key_","lastvalue","units") }
>>   } | ConvertTo-Json -Depth 5
>> 
>>   Write-Host "=== host $id ==="
>>   (Invoke-RestMethod -Uri $url -Method Post -ContentType "application/json-rpc" `
>>     -Headers @{ Authorization = "Bearer $token" } -Body $body).result |
>>     Format-Table name, key_, lastvalue -AutoSize
>> }
=== host 10700 ===

name                    key_                               lastvalue                  
----                    ----                               ---------                  
ICMP ping               icmpping                           1                          
ICMP loss               icmppingloss                       0                          
ICMP response time      icmppingsec                        0.00026533333333333335     
SNMP traps (fallback)   snmptrap.fallback                                             
System contact details  system.contact[sysContact.0]       Unknown                    
System description      system.descr[sysDescr.0]           24 Ports PoE Switch        
Uptime (hardware)       system.hw.uptime[hrSystemUptime.0] 0                          
System location         system.location[sysLocation.0]     Unknown                    
System name             system.name                        8K0AF69PAJCA656            
Uptime (network)        system.net.uptime[sysUpTime.0]     493599                     
System object ID        system.objectid[sysObjectID.0]     .1.3.6.1.4.1.1004849.3.2.10
SNMP agent availability zabbix[host,snmp,available]        1                          
Ports up                cctv.ports.up                      0                          
Ports total             cctv.ports.total                   0                          
Ports down              cctv.ports.down                    0                          


=== host 10691 ===

name                                                   key_                                       lastvalue               
----                                                   ----                                       ---------               
ICMP ping                                              icmpping                                   1                       
ICMP loss                                              icmppingloss                               0                       
ICMP response time                                     icmppingsec                                0.0012566666666666666   
SNMP traps (fallback)                                  snmptrap.fallback                                                  
System contact details                                 system.contact[sysContact.0]               Ruijie@Ruijie.com       
System description                                     system.descr[sysDescr.0]                   RAP6262(G)              
Uptime (hardware)                                      system.hw.uptime[hrSystemUptime.0]         8117088                 
System location                                        system.location[sysLocation.0]             Ruijie                  
System name                                            system.name                                Ruijie                  
Uptime (network)                                       system.net.uptime[sysUpTime.0]             8117092                 
System object ID                                       system.objectid[sysObjectID.0]             .1.3.6.1.4.1.8072.3.2.10
SNMP agent availability                                zabbix[host,snmp,available]                1                       
Linux: SNMP walk EtherLike-MIB interfaces              net.if.duplex.walk                                                 
Linux: SNMP walk network interfaces                    net.if.walk                                                        
Interrupts per second                                  system.cpu.intr[ssRawInterrupts.0]         0                       
SNMP walk CPU load averages                            system.cpu.load.walk                                               
Context switches per second                            system.cpu.switches[ssRawContexts.0]       0                       
SNMP walk system CPUs                                  system.cpu.walk                                                    
Free swap space                                        system.swap.free[memAvailSwap.0]           0                       
Free swap space in %                                   system.swap.pfree[snmp]                    0                       
Total swap space                                       system.swap.total[memTotalSwap.0]          0                       
SNMP walk block devices                                vfs.dev.walk                                                       
SNMP walk mounted filesystems                          vfs.fs.walk                                                        
Available memory                                       vm.memory.available[snmp]                  0                       
Memory (buffers)                                       vm.memory.buffers[memBuffer.0]             0                       
Memory (cached)                                        vm.memory.cached[memCached.0]              0                       
Free memory                                            vm.memory.free[memAvailReal.0]             0                       
Total memory                                           vm.memory.total[memTotalReal.0]            0                       
Memory utilization                                     vm.memory.util[snmp]                       0                       
Load average (15m avg)                                 system.cpu.load.avg15[laLoad.3]            0                       
Load average (1m avg)                                  system.cpu.load.avg1[laLoad.1]             0                       
Load average (5m avg)                                  system.cpu.load.avg5[laLoad.2]             0                       
Number of CPUs                                         system.cpu.num[snmp]                       4                       
Interface br-wan(VLAN 1): Inbound packets discarded    net.if.in.discards[ifInDiscards.9217]      0                       
Interface LAN(): Inbound packets discarded             net.if.in.discards[ifInDiscards.2]         0                       
Interface WAN(): Inbound packets discarded             net.if.in.discards[ifInDiscards.1]         0                       
Interface br-wan(VLAN 1): Inbound packets with errors  net.if.in.errors[ifInErrors.9217]          0                       
Interface LAN(): Inbound packets with errors           net.if.in.errors[ifInErrors.2]             0                       
Interface WAN(): Inbound packets with errors           net.if.in.errors[ifInErrors.1]             0                       
Interface br-wan(VLAN 1): Bits received                net.if.in[ifHCInOctets.9217]               19280                   
Interface LAN(): Bits received                         net.if.in[ifHCInOctets.2]                  0                       
Interface WAN(): Bits received                         net.if.in[ifHCInOctets.1]                  27072                   
Interface br-wan(VLAN 1): Outbound packets discarded   net.if.out.discards[ifOutDiscards.9217]    0                       
Interface LAN(): Outbound packets discarded            net.if.out.discards[ifOutDiscards.2]       0                       
Interface WAN(): Outbound packets discarded            net.if.out.discards[ifOutDiscards.1]       0                       
Interface br-wan(VLAN 1): Outbound packets with errors net.if.out.errors[ifOutErrors.9217]        0                       
Interface LAN(): Outbound packets with errors          net.if.out.errors[ifOutErrors.2]           0                       
Interface WAN(): Outbound packets with errors          net.if.out.errors[ifOutErrors.1]           0                       
Interface br-wan(VLAN 1): Bits sent                    net.if.out[ifHCOutOctets.9217]             6184                    
Interface LAN(): Bits sent                             net.if.out[ifHCOutOctets.2]                0                       
Interface WAN(): Bits sent                             net.if.out[ifHCOutOctets.1]                7904                    
Interface br-wan(VLAN 1): Speed                        net.if.speed[ifHighSpeed.9217]             1000000000              
Interface LAN(): Speed                                 net.if.speed[ifHighSpeed.2]                1000000000              
Interface WAN(): Speed                                 net.if.speed[ifHighSpeed.1]                1000000000              
Interface br-wan(VLAN 1): Operational status           net.if.status[ifOperStatus.9217]           1                       
Interface LAN(): Operational status                    net.if.status[ifOperStatus.2]              2                       
Interface WAN(): Operational status                    net.if.status[ifOperStatus.1]              1                       
Interface br-wan(VLAN 1): Interface type               net.if.type[ifType.9217]                   53                      
Interface LAN(): Interface type                        net.if.type[ifType.2]                      6                       
Interface WAN(): Interface type                        net.if.type[ifType.1]                      6                       
CPU guest time                                         system.cpu.guest[ssCpuRawGuest.0]          0                       
CPU guest nice time                                    system.cpu.guest_nice[ssCpuRawGuestNice.0] 0                       
CPU idle time                                          system.cpu.idle[ssCpuRawIdle.0]            0                       
CPU utilization                                        system.cpu.util[snmp,0]                    0                       
CPU interrupt time                                     system.cpu.interrupt[ssCpuRawInterrupt.0]  0                       
CPU iowait time                                        system.cpu.iowait[ssCpuRawWait.0]          0                       
CPU nice time                                          system.cpu.nice[ssCpuRawNice.0]            0                       
CPU softirq time                                       system.cpu.softirq[ssCpuRawSoftIRQ.0]      0                       
CPU steal time                                         system.cpu.steal[ssCpuRawSteal.0]          0                       
CPU system time                                        system.cpu.system[ssCpuRawSystem.0]        0                       
CPU user time                                          system.cpu.user[ssCpuRawUser.0]            0                       


=======================
Switch CCTV DAHUA

=== host 10700 ===

name                    key_                               lastvalue                         
----                    ----                               ---------                         
Ports up                cctv.ports.up                      0                                 
Ports total             cctv.ports.total                   0                                 
Ports down              cctv.ports.down                    0                                 
Dahua - CPU Usage       cpuUsage.0                         100                               
Dahua - Device CLASS    deviceClass.0                      TS                                
Dahua - Device TYPE     deviceType.0                       24 Ports PoE Switch               
Dahua - IP Adress       ipAddr.0                           192.168.10.245                    
Dahua - MAC Adress      macAddr.0                          3c:e3:6b:14:a8:36                 
Dahua - Machine NAME    machineName.0                      8K0AF69PAJCA656                   
Dahua - Serial NUMBER   serialNumber.0                     8K0AF69PAJCA656                   
Dahua - System VERSION  systemVersion.0                    1.1.0000.10, Build Date:2022-08-17
Device uptime           systeme.uptime[sysUpTime]          507945                            
ICMP ping               icmpping                           1                                 
ICMP loss               icmppingloss                       0                                 
ICMP response time      icmppingsec                        0.000266                          
SNMP traps (fallback)   snmptrap.fallback                                                    
System contact details  system.contact[sysContact.0]       Unknown                           
System description      system.descr[sysDescr.0]           24 Ports PoE Switch               
Uptime (hardware)       system.hw.uptime[hrSystemUptime.0] 0                                 
System location         system.location[sysLocation.0]     Unknown                           
System name             system.name                        8K0AF69PAJCA656                   
Uptime (network)        system.net.uptime[sysUpTime.0]     508105                            
System object ID        system.objectid[sysObjectID.0]     .1.3.6.1.4.1.1004849.3.2.10       
SNMP agent availability zabbix[host,snmp,available]        1        
