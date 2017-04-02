#include <stdio.h>
#include <stddef.h>
#include <sys/types.h>
#include <dirent.h>
#include <signal.h>

// get pid by program name

int main (int argc, char* argv[])
{
DIR *pdir = NULL;
struct dirent *pde = NULL;
FILE *pf = NULL;
char buff[128];
char buff2[128];
char *pstr = NULL;
int pid, ppid;
int n;
int i;

pdir = opendir("/proc");
if (!pdir)
{
perror("open /proc fail.\n");
return -1;
}

while ((pde = readdir(pdir)))
{
if ((pde->d_name[0] < '0')
|| (pde->d_name[0] > '9'))
{
continue;
}
sprintf(buff, "/proc/%s/status", pde->d_name);
pf = fopen(buff, "r");
if (pf)
{
n = fread(buff, 1, 127, pf);
close(pf);
buff[n] = 0;

for (i = 0; i < n; i++)
{
if ('\n' == buff[i])
{
buff[i] = 0;
break;
}
}
//printf("== (%s) ==\n", buff);
n = i; 
for (i = 0; i < n; i++)
{
if ((' ' == buff[i]) || ('\t' == buff[i]))
{
break;
}
}

for (; i < n; i++)
{
if ((' ' != buff[i]) && ('\t' != buff[i]))
{
break;
}
}

//printf("NAME: (%s)\n", buff + i);

if (0 == strcmp(buff + i, argv[1]))
{
printf("%d\n", atoi(pde->d_name));
break;
}
}
}

closedir(pdir);
return 0;
}
