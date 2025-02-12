/**
 * TURAnsel
 *
 * Copyright 2009 The Horde Project (http://www.horde.org)
 * 
 * @license http://opensource.org/licenses/bsd-license.php
 * @author  Michael J. Rubinsky <mrubinsk@horde.org>
 */
#import <Cocoa/Cocoa.h>
@class TURAnselGallery;

typedef enum {
    PERMS_SHOW = 2,
    PERMS_READ = 4,
    PERMS_EDIT = 8,
    PERMS_DELETE = 16
} HORDE_PERMS;

typedef enum {
    TURAnselStateDisconnected = 0,
    TURAnselStateConnected,
    TURAnselStateError,
    TURAnselStateCancelled,
    TURAnselStateWaiting
} TURAnselState;

@interface NSObject (TURAnselDelegate)
- (void)TURAnselDidInitialize;
- (void)TURAnselHadError: (NSError *)error;
@end

#if MAC_OS_X_VERSION_10_6
@interface TURAnsel : NSObject <NSComboBoxDataSource>
#else
@interface TURAnsel : NSObject
#endif         
{
    NSString *userAgent;
    NSString *rpcEndPoint;
    NSString *username;
    NSString *password;
    NSMutableArray *galleryList;
    TURAnselState state;
    id delegate;
    NSLock *lock;
}

@property (readwrite, retain) NSString *rpcEndPoint;
@property (readwrite, retain) NSString *username;
@property (readwrite, retain) NSString *password;

- (id)initWithConnectionParameters: (NSDictionary *)params;
- (void)connect;
- (TURAnselGallery *)getGalleryById: (NSString *)galleryId;
- (TURAnselGallery *)getGalleryByIndex: (NSInteger)index;
- (NSDictionary *)callRPCMethod: (NSString *)methodName withParams: (NSArray *)params withOrder: (NSArray *)order;
- (NSDictionary *)createNewGallery: (NSDictionary *)params;
- (void)cancel;

// Getters/setters
- (void) setState: (TURAnselState)state;
- (TURAnselState)state;
- (id)delegate;
- (void)setDelegate: (id)newDelegate;
@end
